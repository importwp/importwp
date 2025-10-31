import React, { Component } from 'react';
import PropTypes from 'prop-types';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';

import { importer } from '../../services/importer.service';
import { exporter as ExporterService, exporter } from '../../services/exporter.service';
import FieldLabel from '../field-label/FieldLabel';
import InputRadioAccordion from '../InputRadioAccordion/InputRadioAccordion';
import InputRadioAccordionPanel from '../InputRadioAccordionPanel/InputRadioAccordionPanel';
import SelectField from '../SelectField/SelectField';
import InputField from '../InputField/InputField';
import InputButton from '../InputButton/InputButton';

class SetupForm extends Component {
  constructor(props) {
    super(props);

    let template_options = {};

    this.templates = this.props.templates.reduce((obj, key) => {
      obj.push({ value: key.id, label: key.label, options: key.options });
      key.options.forEach((field) => {
        template_options['option_' + key.id + '_' + field.id] = '';
      });
      return obj;
    }, []);

    this.state = {
      name: '',
      template: '',
      template_type: '',
      exporter: '',
      setup_type: 'manual',
      ...template_options,
      saving: false,
      disabled: true,
      upgrade: false,
      exporters: [],
      exporter_config_file: null
    };

    this.onSubmit = this.onSubmit.bind(this);
    this.onChange = this.onChange.bind(this);
  }

  componentDidMount() {
    ExporterService.exporters().then((exporters) => {
      this.setState({
        exporters: exporters.filter(item => item.type?.length > 0 && item.unique_identifier?.length > 0 && item.file_type?.length > 0).reduce((carry, item) => {
          return [...carry, { value: item.id, label: item.name }];
        }, [])
      });
    });
  }

  componentWillUnmount() {
    ExporterService.abort('exporters');
  }

  isDisabled() {
    const { template, name, setup_type, exporter, exporter_config_file } = this.state;

    let disabled = true;
    let upgrade = false;

    if (template) {
      disabled = template.length > 0 ? false : true;

      const current_template = this.templates.find(
        (template_data) => template_data.value === template
      );

      const template_options = current_template ? current_template.options : [];

      // TODO: make sure all template options are filled out, show pro message if value is 'iwp_pro'
      template_options.forEach((template_data) => {
        const val = this.state['option_' + template_data.id];
        if (!val || val === 'iwp_pro') {
          disabled = true;
          if (val === 'iwp_pro') {
            upgrade = true;
          }
        }
      });
    }

    if (setup_type === 'generate') {
      disabled = exporter.length > 0 ? disabled : true;
    }

    if (setup_type === 'upload') {
      disabled = !exporter_config_file ? true : disabled;
    }

    if (name === '') {
      disabled = true;
    }

    this.setState({
      disabled: disabled,
      upgrade: upgrade,
    });
  }

  onChange(name, value) {

    let stateChange = {
      [name]: value
    }

    // reset template_type on template change
    if (name === 'template') {
      stateChange = {
        ...stateChange,
        template_type: '',
      };
    } else if (name === 'setup_type') {
      stateChange = {
        ...stateChange,
        // template: '',
        // template_type: '',
        exporter: '',
      };
    }

    this.setState(stateChange, this.isDisabled);
  }

  onSubmit() {
    this.setState({ saving: true });

    // TODO: only save fields from current template
    const template = this.props.templates.find(
      (item) => item.id === this.state.template
    );
    let template_options = {};
    if (template && template.options) {
      template_options = template.options.reduce((obj, key) => {
        obj[key.id] = this.state['option_' + key.id];
        return obj;
      }, {});
    }

    let exporter_config_file = null;

    new Promise((resolve, reject) => {

      if (this.state.setup_type !== 'upload') {
        resolve();
        return;
      }

      let form_data = new FormData();
      form_data.append('file', this.state.exporter_config_file);

      importer.readExporterConfig(form_data)
        .then((data) => {
          exporter_config_file = JSON.stringify(data.exporter);
          resolve();
        }).catch((error) => {
          reject(error);
        });

    }).then(() => {

      let data = {
        name: this.state.name,
        template: this.state.template,
        template_type: this.state.template_type,
        template_options: template_options,
        setup_type: this.state.setup_type,
        exporter: this.state.exporter,
      };

      if (exporter_config_file !== null) {
        data = {
          ...data,
          exporter_config_file
        };
      }

      importer
        .save(data)
        .then((data) => {
          this.setState({ saving: false });
          this.props.complete(data.id);
        })
        .catch((error) => {
          this.props.onError(error);
          this.setState({
            saving: false,
          });
        });
    }).catch((error) => {
      this.props.onError(error);
      this.setState({
        saving: false,
      });
    });
  }

  render() {
    const { name, template, saving, disabled, upgrade, exporters, exporter } = this.state;
    const current_template = this.templates.find(
      (template_data) => template_data.value === template
    );
    const template_options = current_template ? current_template.options : [];
    return (
      <React.Fragment>
        <div className="iwp-form">
          <form>
            <p className="iwp-heading">Create Importer</p>
            <div className="iwp-form__row">
              <FieldLabel
                id="name"
                field="name"
                label="Name the importer"
                tooltip="Enter the name of the importer, the name is only used to help find your importer."
                display="inline-block"
              />
              <InputField
                id="name"
                name="name"
                type="text"
                className="iwp-form__input"
                value={name}
                onChange={(value) => this.onChange('name', value)}
                placeholder="importer name"
              />
            </div>

            <InputRadioAccordion
              name="setup_type"
              defaultActive="manual"
              onChange={(value) => this.onChange('setup_type', value)}
            >
              <InputRadioAccordionPanel
                value="generate"
                label="Use an existing exporter to populate importer fields."
              >

                <div className="iwp-form__row">
                  <FieldLabel
                    id="exporter"
                    field="exporter"
                    label="Choose exiting Exporter"
                    display="inline-block"
                  />
                  <SelectField
                    id="exporter"
                    name="exporter"
                    placeholder='Choose Exporter'
                    onChange={(value) => this.onChange('exporter', value)}
                    value={exporter}
                    options={<>
                      <option value="">Choose Exporter</option>
                      {exporters.map((row) => (
                        <option key={row.value} value={row.value}>
                          {`#${row.value} - ${row.label}`}
                        </option>
                      ))}
                    </>}
                  />
                </div>

              </InputRadioAccordionPanel>
              <InputRadioAccordionPanel
                value="upload"
                label="Upload importer config file from an exporter"
              >
                <div className='iwp-form__row'>
                  <div className="iwp-field__left">
                    <FieldLabel
                      field="upload_file"
                      id="upload_file"
                      label="Upload File"
                      tooltip="Select the file you wish to import via the file upload input."
                    />
                  </div>
                  <div className="iwp-field__right">
                    <input
                      className="iwp-form__input"
                      id="upload_file"
                      name="file"
                      type="file"
                      onChange={(event) => { this.setState({ exporter_config_file: event.target.files[0] }, this.isDisabled) }}
                    />
                  </div>
                </div>
              </InputRadioAccordionPanel>
              <InputRadioAccordionPanel
                value="manual"
                label="Manually configure the importer."
              />
            </InputRadioAccordion>
            <div className="iwp-form__row">
              <FieldLabel
                id="template"
                field="template"
                label="What are you wanting to import?"
                tooltip="Select from the dropdown what import template you want to use for your import file."
                display="inline-block"
              />
              <SelectField
                id="template"
                name="template"
                placeholder='Choose Template'
                onChange={(value) => this.onChange('template', value)}
                value={template}
                options={<>
                  <option value="">Choose Template</option>
                  {this.templates.map((row) => (
                    <option key={row.value} value={row.value}>
                      {row.label}
                    </option>
                  ))}
                </>}
              />
            </div>

            {template &&
              template_options.map((template_data) => {
                const field_id = `option_${template_data.id}`;
                return (
                  <div key={template_data.id} className="iwp-form__row">
                    <FieldLabel
                      id={field_id}
                      field={field_id}
                      label={template_data.label}
                    />
                    <SelectField
                      id={field_id}
                      name={field_id}
                      className="iwp-form__input"
                      onChange={(value) => this.onChange(field_id, value)}
                      value={this.state['option_' + template_data.id]}
                      options={<>
                        {template_data.options.map((row, i) => (
                          <option
                            key={row.value === 'iwp_pro' ? i : row.value}
                            value={row.value}
                          >
                            {row.label}
                          </option>
                        ))}
                      </>}
                    />
                  </div>
                )
              })}

            {window.iwp.hooks.applyFilters(
              'iwp_after_template_select',
              <UpgradeMessage message="Please upgrade to Import WP Pro into import Custom Post Types." />
            )}


          </form>
        </div>

        <div className="iwp-form__actions">
          <div className="iwp-buttons">
            <InputButton
              theme="primary"
              type="button"
              onClick={this.onSubmit}
              disabled={disabled}
              loading={saving}
            >
              {saving ? 'Saving' : 'Create Importer'}
            </InputButton>
          </div>
        </div>
      </React.Fragment >
    );
  }
}

SetupForm.propTypes = {
  template: PropTypes.string,
  complete: PropTypes.func,
  onError: PropTypes.func,
  templates: PropTypes.array,
};

SetupForm.defaultProps = {
  onError: () => { },
  templates: [],
};

export default SetupForm;
