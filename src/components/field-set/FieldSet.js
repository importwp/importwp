import React from 'react';
import PropTypes from 'prop-types';
import Field from '../field/Field';
import { Tooltip } from 'react-tooltip';
import { connect } from 'react-redux';
import {
  addMapFieldRow,
  getEnabledMap,
  getFieldMap,
  getRepeaterFields,
  removeMapFieldRow,
} from '../../features/importer/importerSlice';

class _FieldSet extends React.PureComponent {
  constructor(props) {
    super(props);

    this.state = {
      show_settings: [],
    };
  }

  addRow(id) {
    this.props.dispatch(addMapFieldRow(id));
  }

  removeRow(id, index) {
    this.props.dispatch(removeMapFieldRow({ id, index }));
  }

  content(groupData, name, parents) {
    const { fields, type } = this.props.group;
    const { show_settings } = this.state;

    const liClass =
      type !== 'repeatable' ? 'iwp-field--border' : 'iwp-field--repeater';

    // Fix: Pass row base from repeater to nested group fields

    groupData =
      !groupData.hasOwnProperty('row_base') &&
        this.props.map.hasOwnProperty('row_base')
        ? { ...groupData, row_base: this.props.map.row_base }
        : groupData;

    return (
      <ul className="iwp-fields">
        {fields.map((field) => {
          const field_set_id = `${parents.join('.')}.${field.id}`;

          if (
            field.type === 'settings' &&
            typeof field.fields !== 'undefined'
          ) {
            return (
              <React.Fragment key={field.id}>
                {this.displayFieldSet(
                  <li className="iwp-field-settings">
                    <button
                      type="button"
                      className="button button-primary"
                      onClick={() => {
                        if (show_settings.indexOf(field_set_id) > -1) {
                          this.setState({
                            show_settings: [
                              ...show_settings.filter(
                                (item) => item !== field_set_id
                              ),
                            ],
                          });
                        } else {
                          this.setState({
                            show_settings: [...show_settings, field_set_id],
                          });
                        }
                      }}
                    >
                      {show_settings.indexOf(field_set_id) !== -1
                        ? 'Hide '
                        : 'Show '}
                      Settings
                    </button>

                    {show_settings.indexOf(field_set_id) !== -1 && (
                      <FieldSet
                        id={`${parents.join('.')}`}
                        group={field}
                        parents={parents}
                        showSelectModal={this.props.showSelectModal}
                        importer_id={this.props.importer_id}
                      />
                    )}
                  </li>,
                  groupData,
                  field,
                  parents
                )}
              </React.Fragment>
            );
          }

          return (
            <React.Fragment key={field.id}>
              {typeof field.fields !== 'undefined'
                ? this.displayFieldSet(
                  <li
                    className={
                      'iwp-field iwp-field--template ' +
                      liClass +
                      ' iwp-field--' +
                      type
                    }
                  >
                    <FieldSet
                      id={`${parents.join('.')}`}
                      group={field}
                      parents={parents}
                      showSelectModal={this.props.showSelectModal}
                      importer_id={this.props.importer_id}
                    />
                  </li>,
                  groupData,
                  field,
                  parents
                )
                : this.display(
                  <li className={'iwp-field iwp-field--template ' + liClass}>
                    <Field
                      field={field}
                      name={name}
                      showSelectModal={this.props.showSelectModal}
                      importer_id={this.props.importer_id}
                    />
                  </li>,
                  groupData,
                  field,
                  parents
                )}
            </React.Fragment>
          );
        })}
      </ul>
    );
  }

  displayFieldSet(content, groupData, group, parents) {
    // Check to see if group is enabled
    const parent_path = [...parents, group.id].join('.');

    if (
      this.props.enabledFields.hasOwnProperty(parent_path) &&
      this.props.enabledFields[parent_path] === false
    ) {
      return '';
    }

    // console.log(group,)

    if (typeof group.condition !== 'undefined') {
      // TODO: make more robust conditional checks,
      //       should check to see if conditional fields are enabled

      if (false === this.checkConditions(group.condition, groupData)) {
        return '';
      }
    }

    return content;
  }

  checkConditions(condition, groupData) {
    // TODO: move to props

    let relation = 'AND';
    if (typeof condition.relation !== 'undefined') {
      relation = condition.relation;

      // convert condition back to array
      condition = Object.keys(condition)
        .filter((key) => key !== 'relation')
        .map(function (key) {
          return condition[key];
        });
    }

    // console.log('condition', condition, this.props.conditionData);

    if (
      Array.isArray(condition[0]) ||
      condition[0].hasOwnProperty('relation')
    ) {
      if (condition.length === 0) {
        return true;
      }

      for (let i = 0; i < condition.length; i++) {
        const row_result = this.checkConditions(condition[i], groupData);

        if ('OR' === relation && true === row_result) {
          // console.log('passed', condition, this.props.conditionData);
          return true;
        }

        if ('AND' === relation && false === row_result) {
          return false;
        }
      }
      if ('OR' === relation) {
        // console.log('failed', condition, this.props.conditionData);
        return false;
      }
      return true;
    } else {
      const operator = condition[1];
      switch (operator) {
        case '*=': // Contains
          if (
            groupData[condition[0]] && true ===
            groupData[condition[0]].includes(condition[2])
          ) {
            return true;
          }
          break;
        case '!*': // Not Contains
          if (
            groupData[condition[0]] && false ===
            groupData[condition[0]].includes(condition[2])
          ) {
            return true;
          }
          break;
        case '==': // Equals
          if (groupData[condition[0]] === condition[2]) {
            return true;
          }
          break;
        case '!=': // Not Equals
          if (groupData[condition[0]] !== condition[2]) {
            return true;
          }
          break;
      }
    }

    return false;
  }

  display(content, groupData, field, parents) {
    // toggle display of field based on enabled fields settings
    const parent_path = [...parents, field.id].join('.');
    // const enable_key = 'enable_' + parent_path;
    if (
      this.props.enabledFields.hasOwnProperty(parent_path) &&
      this.props.enabledFields[parent_path] === false
    ) {
      return '';
    }

    if (typeof field.condition !== 'undefined') {
      // TODO: make more robust conditional checks,
      //       should check to see if conditional fields are enabled

      if (false === this.checkConditions(field.condition, groupData)) {
        return '';
      }
    }

    return content;
  }

  removeGroupIndex(data, offset = 1) {
    return Object.keys(data).reduce((obj, key) => {
      const parts = key.split('.');
      obj[parts.splice(offset).join('.')] = data[key];
      return obj;
    }, {});
  }

  render() {
    const { type, id } = this.props.group;
    const { map } = this.props;

    let parents = [...this.props.parents];

    if (type === 'repeatable') {
      parents.push(id);
      let key = parents.join('.');
      // let groupData = Object.keys(map).filter(value => {
      //   return value.startsWith(key);
      // });
      // console.log('groupData', groupData, map, id);
      // console.log(id, key, map);
      return (
        <div className="iwp-repeater__wrapper">
          <ul className="iwp-repeater">
            {this.props.repeaterMap.map((record, index) => {
              const tempParents = [...parents, index];
              return (
                <li key={`${id}_${index}`} className="iwp-repeater__row">
                  <span className="iwp-repeater__index">
                    <span>{index + 1}</span>
                  </span>
                  {this.content(this.removeGroupIndex(record, 2), key + '.' + index, tempParents)}
                  <div className="iwp-field iwp-buttons iwp-repeater__buttons">
                    <Tooltip
                      id={'iwp-delete-tooltip-' + id + '-' + index}
                      effect="solid"
                      delayHide={300}
                      className="iwp-react-tooltip"
                    >
                      Delete Row
                    </Tooltip>

                    <button
                      onClick={() => this.removeRow(id, index)}
                      type="button"
                      title="Delete Row"
                      data-tooltip-content="Delete Row"
                      data-tooltip-id={'iwp-delete-tooltip-' + id + '-' + index}
                    >
                      Delete Row
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
          <div className="iwp-repeater__actions">
            <div className="iwp-buttons">
              <button
                type="button"
                className="button button-secondary"
                onClick={() => this.addRow(id)}
              >
                Add Row
              </button>
            </div>
          </div>
        </div>
      );
    } else {
      parents.push(id);

      let field_key = parents.join('.');

      let groupData = {};

      if (typeof map === 'undefined') {
        return '';
      }

      let tmp = Object.keys(map).filter((value) => {
        return value.startsWith(field_key + '.');
      });

      groupData = tmp.reduce((obj, key) => {

        const pos = key.indexOf(id);
        if (pos > -1) {
          obj[key.substring(pos)] = map[key];
        }

        return obj;
      }, {});

      return this.content(this.removeGroupIndex(groupData, 1), field_key, parents);
    }
  }
}

_FieldSet.propTypes = {
  group: PropTypes.object.isRequired,
  showSelectModal: PropTypes.func,
  parents: PropTypes.array,
  enabledFields: PropTypes.object,
  importer_id: PropTypes.number,
};

_FieldSet.defaultProps = {
  parents: [],
  enabledFields: {},
  removeRow: () => { },
  addRow: () => { },
};

const mapStateToProps = (state, props) => ({
  enabledFields: getEnabledMap(state, props.id),
  map: getFieldMap(state, props.id),
  repeaterMap: getRepeaterFields(state, props.id),
});

const FieldSet = connect(mapStateToProps)(_FieldSet);

export default FieldSet;
