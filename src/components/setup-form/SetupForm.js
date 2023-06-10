import React, { Component } from 'react';
import PropTypes from 'prop-types';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';

import { createHooks } from '@wordpress/hooks';
import { importer } from '../../services/importer.service';
import FieldLabel from '../field-label/FieldLabel';

const hooks = createHooks();

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
      ...template_options,
      saving: false,
      disabled: true,
      upgrade: false,
    };

    this.onSubmit = this.onSubmit.bind(this);
    this.onChange = this.onChange.bind(this);
  }

  isDisabled() {
    const { template, name } = this.state;

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

    if (name === '') {
      disabled = true;
    }

    this.setState({
      disabled: disabled,
      upgrade: upgrade,
    });
  }

  onChange(event) {
    const { name, value } = event.target;

    // reset template_type on template change
    if (name === 'template') {
      this.setState({ template_type: '' });
    }

    this.setState({ [name]: value }, this.isDisabled);
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

    // const template_options = Object.keys(this.state)
    //   .filter(item => item.startsWith('option_'))
    //   .reduce((obj, key) => {
    //     obj[key.substring('option_'.length)] = this.state[key];
    //     return obj;
    //   }, {});

    // console.log('ASD');
    importer
      .save({
        name: this.state.name,
        template: this.state.template,
        template_type: this.state.template_type,
        template_options: template_options,
      })
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
  }

  render() {
    const { template, saving, disabled, upgrade } = this.state;
    const current_template = this.templates.find(
      (template_data) => template_data.value === template
    );
    const template_options = current_template ? current_template.options : [];
    return (
      <React.Fragment>
        <div className="iwp-form">
          <form>
            <p className="iwp-heading">Create Importer</p>
            <div className="iwp-form__row ">
              <FieldLabel
                id="name"
                field="name"
                label="Name the importer"
                tooltip="Enter the name of the importer, the name is only used to help find your importer."
                display="inline-block"
              />
              <input
                id="name"
                name="name"
                type="text"
                className="iwp-form__input"
                onChange={this.onChange}
                placeholder="importer name"
              />
            </div>
            <div className="iwp-form__row">
              <FieldLabel
                id="template"
                field="template"
                label="What are you wanting to import?"
                tooltip="Select from the dropdown what import template you want to use for your import file."
                display="inline-block"
              />
              <select
                id="template"
                name="template"
                className="iwp-form__input"
                onChange={this.onChange}
                value={template}
              >
                <option value="">Choose Template</option>
                {this.templates.map((row) => (
                  <option key={row.value} value={row.value}>
                    {row.label}
                  </option>
                ))}
              </select>
            </div>

            {template &&
              template_options.map((template_data) => (
                <div key={template_data.id} className="iwp-form__row">
                  <label className="iwp-form__label">
                    {template_data.label}
                  </label>
                  <select
                    name={'option_' + template_data.id}
                    className="iwp-form__input"
                    onChange={this.onChange}
                    value={this.state['option_' + template_data.id]}
                  >
                    {template_data.options.map((row, i) => (
                      <option
                        key={row.value === 'iwp_pro' ? i : row.value}
                        value={row.value}
                      >
                        {row.label}
                      </option>
                    ))}
                  </select>
                </div>
              ))}

            {window.iwp.hooks.applyFilters(
              'iwp_after_template_select',
              <UpgradeMessage message="Please upgrade to Import WP Pro into import Custom Post Types." />
            )}
          </form>
        </div>

        <div className="iwp-form__actions">
          <div className="iwp-buttons">
            <button
              className="button button-primary"
              type="button"
              onClick={this.onSubmit}
              disabled={disabled}
            >
              {saving && <span className="spinner is-active"></span>}
              {saving ? 'Saving' : 'Create Importer'}
            </button>
          </div>
        </div>
      </React.Fragment>
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
  onError: () => {},
  templates: [],
};

export default SetupForm;
