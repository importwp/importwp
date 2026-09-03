import React, { useCallback, useEffect, useMemo, useState } from 'react';
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

function SetupForm({ complete, onError = () => { }, templates = [] }) {
  const { templatesList, initialTemplateOptions } = useMemo(() => {
    const templateOptions = {};

    const list = templates.reduce((obj, key) => {
      obj.push({ value: key.id, label: key.label, options: key.options });
      key.options.forEach((field) => {
        templateOptions['option_' + key.id + '_' + field.id] = '';
      });
      return obj;
    }, []);

    return {
      templatesList: list,
      initialTemplateOptions: templateOptions,
    };
  }, [templates]);

  const [name, setName] = useState('');
  const [template, setTemplate] = useState('');
  const [template_type, setTemplateType] = useState('');
  const [exporterValue, setExporterValue] = useState('');
  const [setup_type, setSetupType] = useState('manual');
  const [templateOptions, setTemplateOptions] = useState(initialTemplateOptions);
  const [saving, setSaving] = useState(false);
  const [exporters, setExporters] = useState([]);
  const [exporter_config_file, setExporterConfigFile] = useState(null);

  const { disabled, upgrade } = useMemo(() => {
    let nextDisabled = true;
    let nextUpgrade = false;

    if (template) {
      nextDisabled = template.length > 0 ? false : true;

      const current_template = templatesList.find(
        (template_data) => template_data.value === template
      );

      const currentTemplateOptions = current_template ? current_template.options : [];

      // TODO: make sure all template options are filled out, show pro message if value is 'iwp_pro'
      currentTemplateOptions.forEach((template_data) => {
        const val = templateOptions['option_' + template_data.id];
        if (!val || val === 'iwp_pro') {
          nextDisabled = true;
          if (val === 'iwp_pro') {
            nextUpgrade = true;
          }
        }
      });
    }

    if (setup_type === 'generate') {
      nextDisabled = exporterValue.length > 0 ? nextDisabled : true;
    }

    if (setup_type === 'upload') {
      nextDisabled = !exporter_config_file ? true : nextDisabled;
    }

    if (name === '') {
      nextDisabled = true;
    }

    return { disabled: nextDisabled, upgrade: nextUpgrade };
  }, [exporterValue, exporter_config_file, name, setup_type, template, templateOptions, templatesList]);

  useEffect(() => {
    ExporterService.exporters().then((exporterList) => {
      setExporters(
        exporterList.filter(item => item.type?.length > 0 && item.unique_identifier?.length > 0 && item.file_type?.length > 0).reduce((carry, item) => {
          return [...carry, { value: item.id, label: item.name }];
        }, [])
      );
    });

    return () => {
      ExporterService.abort('exporters');
    };
  }, []);

  const onChange = useCallback((fieldName, value) => {
    if (fieldName === 'name') {
      setName(value);
    } else if (fieldName === 'template') {
      setTemplate(value);
      setTemplateType('');
    } else if (fieldName === 'setup_type') {
      setSetupType(value);
      setExporterValue('');
    } else if (fieldName === 'exporter') {
      setExporterValue(value);
    } else {
      setTemplateOptions((prev) => ({
        ...prev,
        [fieldName]: value,
      }));
    }
  }, []);

  const onSubmit = useCallback(() => {
    setSaving(true);

    // TODO: only save fields from current template
    const currentTemplate = templates.find(
      (item) => item.id === template
    );
    let savedTemplateOptions = {};
    if (currentTemplate && currentTemplate.options) {
      savedTemplateOptions = currentTemplate.options.reduce((obj, key) => {
        obj[key.id] = templateOptions['option_' + key.id];
        return obj;
      }, {});
    }

    let savedExporterConfigFile = null;

    new Promise((resolve, reject) => {

      if (setup_type !== 'upload') {
        resolve();
        return;
      }

      let form_data = new FormData();
      form_data.append('file', exporter_config_file);

      importer.readExporterConfig(form_data)
        .then((data) => {
          savedExporterConfigFile = JSON.stringify(data.exporter);
          resolve();
        }).catch((error) => {
          reject(error);
        });

    }).then(() => {

      let data = {
        name: name,
        template: template,
        template_type: template_type,
        template_options: savedTemplateOptions,
        setup_type: setup_type,
        exporter: exporterValue,
      };

      if (savedExporterConfigFile !== null) {
        data = {
          ...data,
          exporter_config_file: savedExporterConfigFile
        };
      }

      importer
        .save(data)
        .then((savedData) => {
          setSaving(false);
          complete(savedData.id);
        })
        .catch((error) => {
          onError(error);
          setSaving(false);
        });
    }).catch((error) => {
      onError(error);
      setSaving(false);
    });
  }, [complete, exporterValue, exporter_config_file, name, onError, setup_type, template, templateOptions, template_type, templates]);

  const current_template = templatesList.find(
    (template_data) => template_data.value === template
  );
  const currentTemplateOptions = current_template ? current_template.options : [];

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
              onChange={(value) => onChange('name', value)}
              placeholder="importer name"
            />
          </div>

          <InputRadioAccordion
            name="setup_type"
            defaultActive="manual"
            onChange={(value) => onChange('setup_type', value)}
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
                  onChange={(value) => onChange('exporter', value)}
                  value={exporterValue}
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
                    onChange={(event) => { setExporterConfigFile(event.target.files[0]); }}
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
              onChange={(value) => onChange('template', value)}
              value={template}
              options={<>
                <option value="">Choose Template</option>
                {templatesList.map((row) => (
                  <option key={row.value} value={row.value}>
                    {row.label}
                  </option>
                ))}
              </>}
            />
          </div>

          {template &&
            currentTemplateOptions.map((template_data) => {
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
                    onChange={(value) => onChange(field_id, value)}
                    value={templateOptions['option_' + template_data.id]}
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
            onClick={onSubmit}
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

SetupForm.propTypes = {
  template: PropTypes.string,
  complete: PropTypes.func,
  onError: PropTypes.func,
  templates: PropTypes.array,
};

export default SetupForm;
