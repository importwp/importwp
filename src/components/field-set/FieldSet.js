import React, { useCallback, useMemo, useState } from 'react';
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

function FieldSet({
  group,
  showSelectModal,
  parents = [],
  enabledFields = {},
  importer_id,
  map,
  repeaterMap,
  dispatch,
}) {
  const initialRows = useMemo(() => {
    return group &&
      group.type === 'repeatable' &&
      Array.isArray(repeaterMap) &&
      repeaterMap.length > 0 &&
      repeaterMap.length <= 3
      ? repeaterMap.map((_, index) => index)
      : [];
  }, [group, repeaterMap]);

  const [show_settings, setShowSettings] = useState([]);
  const [expandedRows, setExpandedRows] = useState(initialRows);

  const addRow = useCallback((id) => {
    const newIndex = Array.isArray(repeaterMap) ? repeaterMap.length : 0;
    dispatch(addMapFieldRow(id));
    setExpandedRows((current) =>
      current.includes(newIndex) ? current : [...current, newIndex]
    );
  }, [dispatch, repeaterMap]);

  const removeRow = useCallback((id, index) => {
    dispatch(removeMapFieldRow({ id, index }));
    setExpandedRows((current) =>
      current
        .filter((rowIndex) => rowIndex !== index)
        .map((rowIndex) => (rowIndex > index ? rowIndex - 1 : rowIndex))
    );
  }, [dispatch]);

  const isRowExpanded = useCallback((index) => {
    return expandedRows.indexOf(index) !== -1;
  }, [expandedRows]);

  const toggleRow = useCallback((index) => {
    setExpandedRows((current) => {
      if (current.indexOf(index) !== -1) {
        return current.filter((rowIndex) => rowIndex !== index);
      }
      return [...current, index];
    });
  }, []);

  const expandAll = useCallback(() => {
    setExpandedRows(repeaterMap.map((_, index) => index));
  }, [repeaterMap]);

  const collapseAll = useCallback(() => {
    setExpandedRows([]);
  }, []);

  const getRowSummary = useCallback((record, key, index) => {
    const prefix = `${key}.${index}.`;
    const fieldKey = record[`${prefix}key`] || '';
    const fieldType = record[`${prefix}_field_type`] || '';
    const value = record[`${prefix}value`] || '';

    const parts = [];
    if (fieldKey) {
      parts.push(fieldKey);
    }
    if (fieldType && fieldType !== 'text') {
      parts.push(`(${fieldType})`);
    }
    if (value) {
      const shortValue =
        value.length > 60 ? `${value.substring(0, 57)}…` : value;
      parts.push(`→ ${shortValue}`);
    }

    return parts.length > 0 ? parts.join(' ') : `Row ${index + 1}`;
  }, []);

  const checkConditions = useCallback((condition, groupData) => {
    let relation = 'AND';
    let currentCondition = condition;
    if (typeof currentCondition.relation !== 'undefined') {
      relation = currentCondition.relation;

      currentCondition = Object.keys(currentCondition)
        .filter((key) => key !== 'relation')
        .map(function (key) {
          return currentCondition[key];
        });
    }

    if (
      Array.isArray(currentCondition[0]) ||
      currentCondition[0].hasOwnProperty('relation')
    ) {
      if (currentCondition.length === 0) {
        return true;
      }

      for (let i = 0; i < currentCondition.length; i++) {
        const row_result = checkConditions(currentCondition[i], groupData);

        if ('OR' === relation && true === row_result) {
          return true;
        }

        if ('AND' === relation && false === row_result) {
          return false;
        }
      }
      if ('OR' === relation) {
        return false;
      }
      return true;
    } else {
      const operator = currentCondition[1];
      switch (operator) {
        case '*=': // Contains
          if (
            groupData[currentCondition[0]] && true ===
            groupData[currentCondition[0]].includes(currentCondition[2])
          ) {
            return true;
          }
          break;
        case '!*': // Not Contains
          if (
            groupData[currentCondition[0]] && false ===
            groupData[currentCondition[0]].includes(currentCondition[2])
          ) {
            return true;
          }
          break;
        case '==': // Equals
          if (groupData[currentCondition[0]] === currentCondition[2]) {
            return true;
          }
          break;
        case '!=': // Not Equals
          if (groupData[currentCondition[0]] !== currentCondition[2]) {
            return true;
          }
          break;
      }
    }

    return false;
  }, []);

  const displayFieldSet = useCallback((content, groupData, groupItem, parentList) => {
    const parent_path = [...parentList, groupItem.id].join('.');

    if (
      enabledFields.hasOwnProperty(parent_path) &&
      enabledFields[parent_path] === false
    ) {
      return '';
    }

    if (typeof groupItem.condition !== 'undefined') {
      if (false === checkConditions(groupItem.condition, groupData)) {
        return '';
      }
    }

    return content;
  }, [checkConditions, enabledFields]);

  const display = useCallback((content, groupData, field, parentList) => {
    const parent_path = [...parentList, field.id].join('.');
    if (
      enabledFields.hasOwnProperty(parent_path) &&
      enabledFields[parent_path] === false
    ) {
      return '';
    }

    if (typeof field.condition !== 'undefined') {
      if (false === checkConditions(field.condition, groupData)) {
        return '';
      }
    }

    return content;
  }, [checkConditions, enabledFields]);

  const removeGroupIndex = useCallback((data, offset = 1) => {
    return Object.keys(data).reduce((obj, key) => {
      const parts = key.split('.');
      obj[parts.splice(offset).join('.')] = data[key];
      return obj;
    }, {});
  }, []);

  const content = useCallback((groupData, name, parentList) => {
    const { fields, type } = group;

    const liClass =
      type !== 'repeatable' ? 'iwp-field--border' : 'iwp-field--repeater';

    const resolvedGroupData =
      !groupData.hasOwnProperty('row_base') &&
        map.hasOwnProperty('row_base')
        ? { ...groupData, row_base: map.row_base }
        : groupData;

    return (
      <ul className="iwp-fields">
        {fields.map((field) => {
          const field_set_id = `${parentList.join('.')}.${field.id}`;

          if (
            field.type === 'settings' &&
            typeof field.fields !== 'undefined'
          ) {
            return (
              <React.Fragment key={field.id}>
                {displayFieldSet(
                  <li className="iwp-field-settings">
                    <button
                      type="button"
                      className="button button-primary"
                      onClick={() => {
                        if (show_settings.indexOf(field_set_id) > -1) {
                          setShowSettings([
                            ...show_settings.filter(
                              (item) => item !== field_set_id
                            ),
                          ]);
                        } else {
                          setShowSettings([...show_settings, field_set_id]);
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
                        id={`${parentList.join('.')}`}
                        group={field}
                        parents={parentList}
                        showSelectModal={showSelectModal}
                        importer_id={importer_id}
                      />
                    )}
                  </li>,
                  resolvedGroupData,
                  field,
                  parentList
                )}
              </React.Fragment>
            );
          }

          return (
            <React.Fragment key={field.id}>
              {typeof field.fields !== 'undefined'
                ? displayFieldSet(
                  <li
                    className={
                      'iwp-field iwp-field--template ' +
                      liClass +
                      ' iwp-field--' +
                      type
                    }
                  >
                    <FieldSet
                      id={`${parentList.join('.')}`}
                      group={field}
                      parents={parentList}
                      showSelectModal={showSelectModal}
                      importer_id={importer_id}
                    />
                  </li>,
                  resolvedGroupData,
                  field,
                  parentList
                )
                : display(
                  <li className={'iwp-field iwp-field--template ' + liClass}>
                    <Field
                      field={field}
                      name={name}
                      showSelectModal={showSelectModal}
                      importer_id={importer_id}
                    />
                  </li>,
                  resolvedGroupData,
                  field,
                  parentList
                )}
            </React.Fragment>
          );
        })}
      </ul>
    );
  }, [display, displayFieldSet, group, importer_id, map, showSelectModal, show_settings]);

  const { type, id } = group;

  let currentParents = [...parents];

  if (type === 'repeatable') {
    currentParents.push(id);
    let key = currentParents.join('.');
    const rowCount = repeaterMap.length;
    return (
      <div className="iwp-repeater__wrapper">
        {rowCount > 5 && (
          <div className="iwp-repeater__bulk-actions iwp-buttons">
            <button
              type="button"
              className="button button-link"
              onClick={expandAll}
            >
              Expand All
            </button>
            <button
              type="button"
              className="button button-link"
              onClick={collapseAll}
            >
              Collapse All
            </button>
          </div>
        )}
        <ul className="iwp-repeater">
          {repeaterMap.map((record, index) => {
            const tempParents = [...currentParents, index];
            const expanded = isRowExpanded(index);
            return (
              <li
                key={`${id}_${index}`}
                className={
                  'iwp-repeater__row' +
                  (expanded ? ' iwp-repeater__row--expanded' : ' iwp-repeater__row--collapsed')
                }
              >
                <span className="iwp-repeater__index">
                  <span>{index + 1}</span>
                </span>
                <div className="iwp-repeater__summary">
                  <button
                    type="button"
                    className="iwp-repeater__toggle"
                    aria-expanded={expanded}
                    onClick={() => toggleRow(index)}
                  >
                    <span className="iwp-repeater__toggle-icon" aria-hidden="true">
                      {expanded ? '▾' : '▸'}
                    </span>
                    <span className="iwp-repeater__summary-text">
                      {getRowSummary(record, key, index)}
                    </span>
                  </button>
                </div>
                {expanded &&
                  content(
                    removeGroupIndex(record, 2),
                    key + '.' + index,
                    tempParents
                  )}
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
                    onClick={() => removeRow(id, index)}
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
              onClick={() => addRow(id)}
            >
              Add Row
            </button>
          </div>
        </div>
      </div>
    );
  } else {
    currentParents.push(id);

    let field_key = currentParents.join('.');

    if (typeof map === 'undefined') {
      return '';
    }

    let tmp = Object.keys(map).filter((value) => {
      return value.startsWith(field_key + '.');
    });

    const groupData = tmp.reduce((obj, key) => {

      const pos = key.indexOf(id);
      if (pos > -1) {
        obj[key.substring(pos)] = map[key];
      }

      return obj;
    }, {});

    return content(removeGroupIndex(groupData, 1), field_key, currentParents);
  }
}

FieldSet.propTypes = {
  group: PropTypes.object.isRequired,
  showSelectModal: PropTypes.func,
  parents: PropTypes.array,
  enabledFields: PropTypes.object,
  importer_id: PropTypes.number,
};

const mapStateToProps = (state, props) => ({
  enabledFields: getEnabledMap(state, props.id),
  map: getFieldMap(state, props.id),
  repeaterMap: getRepeaterFields(state, props.id),
});

export default connect(mapStateToProps)(FieldSet);
