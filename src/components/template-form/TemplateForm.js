import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import {
  setTemplate,
  resetEnabled,
  selectMap,
  resetRepeater,
  resetTemplate,
  fetchFieldPreview,
  clearPreview,
} from '../../features/importer/importerSlice';

import FieldGroup from '../field-group/FieldGroup';
import Modal from '../modal/Modal';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';

import { importer } from '../../services/importer.service';
import DataSelector from '../data-selector/DataSelector';
import NoticeList from '../notice-list/NoticeList';
import { store } from '../../store';

class TemplateForm extends PureComponent {
  constructor(props) {
    super(props);

    this.repeaterTemplates = {};
    this.defaultValues = {};

    this.state = {
      showSelectModal: false,
      showSelectModalSubPath: '',
      selectModalField: '',
      saving: false,
      disabled: false,
      loaded: false,
    };

    this.save = this.save.bind(this);
    this.onSave = this.onSave.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.getGroupValues = this.getGroupValues.bind(this);
    this.showSelectModal = this.showSelectModal.bind(this);
    this.closeSelectModal = this.closeSelectModal.bind(this);
    this.setAndCloseSelectModal = this.setAndCloseSelectModal.bind(this);
  }

  generateRepeaterTemplates(templateFields, group) {
    const prefix = group + '.{iwpr_template}.';
    const templateKeys = Object.keys(templateFields)
      .filter((fieldKey) => fieldKey.startsWith(prefix))
      .reduce((obj, key) => {
        obj[key.substring(prefix.length)] = templateFields[key];
        delete templateFields[key];
        return obj;
      }, {});
    return templateKeys;
  }

  recursiveFieldSearch(data, path = [], output = [], join = '') {
    let result = [];
    const keys = Object.keys(data);

    let basePath = [...path];

    if (join.length > 0) {
      basePath.push(join);
      join = '';
    }

    for (let i = 0; i < keys.length; i++) {
      const record = data[keys[i]];
      let tempPath = [...basePath];
      tempPath.push(record.id);
      if (record.hasOwnProperty('type') && record.type === 'repeatable') {
        result.push(
          ...this.recursiveFieldSearch(
            record.fields,
            tempPath,
            output,
            '{iwpr_template}'
          )
        );
        this.repeaterTemplates[[...tempPath].join()] = null;
        tempPath.push('_index');
        result.push(tempPath);
      } else if (record.hasOwnProperty('fields')) {
        result.push(
          ...this.recursiveFieldSearch(record.fields, tempPath, output)
        );
      } else {
        result.push(tempPath);
        if (record.default) {
          this.defaultValues[[...tempPath].join('.')] = record.default;
        }
      }
    }

    return result;
  }

  getGroupValues(groupName) {
    const keyPrefix = 'value_' + groupName + '.';
    const result = Object.keys(this.state)
      .filter((key) => key.startsWith(keyPrefix))
      .reduce((obj, key) => {
        obj[key.substring('value_'.length)] = this.state[key];
        return obj;
      }, {});
    return result;
  }

  showSelectModal(fieldName, sub_path = '') {
    console.log('showSelectModal', fieldName, sub_path);
    this.setState({
      showSelectModal: !this.state.showSelectModal,
      showSelectModalSubPath: sub_path,
      selectModalField: fieldName,
    });
  }

  setAndCloseSelectModal(selection) {
    this.props.dispatch(
      setTemplate({ [this.state.selectModalField]: selection })
    );

    // TODO: why doesnt this update?
    this.props.dispatch(
      fetchFieldPreview({
        id: this.props.id,
        fields: {
          [this.state.selectModalField]: selection,
        },
      })
    );

    this.closeSelectModal();
  }

  closeSelectModal() {
    this.setState({
      showSelectModal: false,
      selectModalField: '',
    });
  }

  save(callback = () => {}) {
    this.setState({ saving: true });
    const { id } = this.props;

    const data = store.getState();

    const map_data = Object.keys(data.importer.template)
      .filter((key) => {
        return !key.includes('{iwpr_template}');
      })
      .reduce((obj, key) => {
        obj[key] = data.importer.template[key];
        return obj;
      }, {});

    // TODO: enable data is currently stored in FieldGroup
    const enable_data = Object.keys(data.importer.enabled).reduce(
      (obj, key) => {
        obj[key] = data.importer.enabled[key];
        return obj;
      },
      {}
    );

    importer
      .save({
        id: id,
        map: map_data,
        enabled: enable_data,
      })
      .then(() => {
        this.setState({ saving: false });
        callback();
      })
      .catch((error) => {
        this.setState({
          saving: false,
          errors: [
            ...this.state.errors,
            {
              section: 'setup',
              message: error,
            },
          ],
        });
      });
  }

  onSave() {
    this.save();
  }

  onSubmit() {
    this.save(() => {
      this.props.complete();
    });
  }

  async componentDidMount() {
    // TODO: Get Template from rest
    try {
      const template_group = await importer.template(this.props.id);
      if (!template_group) {
        // TODO: Add error message
        this.props.onError(
          'Importer Template could not be found: ' + this.props.template
        );
      }

      this.template_groups = template_group ? template_group.map : [];

      let templateState = {};
      let enabledFields = {};

      if (this.template_groups) {
        this.recursiveFieldSearch(this.template_groups).map((field) => {
          const fieldKey = field.join('.');
          if (field[field.length - 1] === '_index') {
            templateState[fieldKey] = 0;
          } else {
            templateState[fieldKey] = this.defaultValues[fieldKey]
              ? this.defaultValues[fieldKey]
              : '';
          }
        });

        // Generate repeater templates
        Object.keys(this.repeaterTemplates).map((group) => {
          this.repeaterTemplates[group] = this.generateRepeaterTemplates(
            templateState,
            group
          );
        });

        let tmp = [];
        this.template_groups.forEach((group) => {
          if (group.type === 'group') {
            group.fields.forEach((field) => {
              if (!field.hasOwnProperty('core') || field.core === false) {
                enabledFields = {
                  ...enabledFields,
                  [`${group.id}.${field.id}`]: false,
                };
              }
            });
          }

          // Clone: https://scotch.io/bar-talk/copying-objects-in-javascript
          let group_clone = JSON.parse(JSON.stringify(group));

          // Remove row_base file for none xml imports
          if (this.props.parser !== 'xml') {
            const tmp_fields = group_clone.fields;
            group_clone.fields = tmp_fields.filter(
              (field) => field.id !== 'row_base'
            );
          }

          tmp = [...tmp, group_clone];
        });

        this.template_groups = [...tmp];
      }

      // setup enabled field state

      enabledFields = {
        ...enabledFields,
        ...Object.keys(this.props.enabled).reduce((obj, key) => {
          obj[key] = this.props.enabled[key];
          return obj;
        }, {}),
      };

      this.props.dispatch(resetEnabled(enabledFields));

      // setup template field value state
      const template = Object.keys(this.props.map).reduce((obj, key) => {
        obj[key] = this.props.map[key];
        return obj;
      }, {});

      this.props.dispatch(
        resetTemplate({
          ...templateState,
          ...template,
        })
      );
      this.props.dispatch(clearPreview());

      // trigger field previews
      this.props.dispatch(
        fetchFieldPreview({
          id: this.props.id,
          fields: template,
        })
      );

      // store field group templates
      this.props.dispatch(resetRepeater({ ...this.repeaterTemplates }));

      this.setState({
        loaded: true,
      });
    } catch (e) {
      this.props.onError('Error: ' + e);
      this.setState({ loaded: true });
      return;
    }
  }

  componentWillUnmount() {
    importer.abort();
  }

  render() {
    const { id, parser, settings } = this.props;
    const { disabled, saving } = this.state;
    const title =
      parser === 'csv'
        ? 'CSV Data Selector'
        : parser === 'xml'
        ? 'XML Data Selector'
        : 'Data Selector';

    if (!this.state.loaded) {
      return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
    }
    return (
      <React.Fragment>
        <Modal
          onClose={this.closeSelectModal}
          show={this.state.showSelectModal}
          title={title}
        >
          <DataSelector
            onSelect={this.setAndCloseSelectModal}
            id={id}
            parser={parser}
            settings={settings}
            selection={this.state['value_' + this.state.selectModalField]}
            subPath={this.state.showSelectModalSubPath}
          ></DataSelector>
        </Modal>

        {this.template_groups.length > 0 &&
          this.template_groups.map((group) => {
            return (
              <FieldGroup
                key={group.id}
                group={group}
                showSelectModal={this.showSelectModal}
                importer_id={id}
              />
            );
          })}

        {window.iwp.hooks.applyFilters(
          'iwp_template_form_end',
          <div className="iwp-form iwp-form--mb">
            <p className="iwp-heading">Custom Fields</p>
            <UpgradeMessage message="Please upgrade to Import WP Pro to import custom fields." />
          </div>
        )}

        <div className="iwp-form__actions">
          <div className="iwp-buttons">
            <button
              className="button button-secondary"
              type="button"
              onClick={this.onSave}
              disabled={disabled}
            >
              {saving && <span className="spinner is-active"></span>}
              {saving ? 'Saving' : 'Save'}
            </button>{' '}
            <button
              className="button button-primary"
              type="button"
              onClick={this.onSubmit}
              disabled={disabled}
            >
              {saving && <span className="spinner is-active"></span>}
              {saving ? 'Saving' : 'Save & Continue'}
            </button>
          </div>
        </div>
      </React.Fragment>
    );
  }
}

TemplateForm.propTypes = {
  id: PropTypes.number,
  complete: PropTypes.func,
  parser: PropTypes.string,
  settings: PropTypes.object,
  map: PropTypes.object,
  enabled: PropTypes.object,
  onError: PropTypes.func,
  template: PropTypes.string,
  pro: PropTypes.bool,
  templates: PropTypes.array,
};

TemplateForm.defaultProps = {
  map: {},
  enabled: {},
  onError: () => {},
  pro: false,
  templates: [],
};

const mapStateToProps = (state) => ({
  map: selectMap(state),
});

export default connect(mapStateToProps)(TemplateForm);
