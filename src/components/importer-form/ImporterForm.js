import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Switch from 'react-switch';

import './ImporterForm.scss';
import { importer } from '../../services/importer.service';
import SettingField from '../setting-field/SettingField';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';
import FieldLabel from '../field-label/FieldLabel';
import ImportFilter from '../import-filter/ImportFilter';

const default_schedule = {
  setting_cron_disabled: false,
  setting_cron_schedule: 'month',
  setting_cron_day: 0,
  setting_cron_hour: 0,
  setting_cron_minute: 0,
  setting_run_fetch: false,
};

class ImporterForm extends Component {
  constructor(props) {
    super(props);

    const template = this.props.templates.find((data) => {
      return data.id === props.template;
    });
    this.template_settings = template ? template['settings'] : [];

    let settings_state = {};
    if (this.template_settings) {
      this.template_settings.forEach((field) => {
        settings_state['setting_' + field.id] =
          props.settings && props.settings[field.id]
            ? props.settings[field.id]
            : '';
      });
    }

    this.state = {
      ...settings_state,
      setting_import_method:
        props.settings && props.settings.import_method
          ? props.settings.import_method
          : 'run',
      setting_cron_schedule:
        props.settings && props.settings.cron_schedule
          ? props.settings.cron_schedule
          : 'month',
      setting_cron_day:
        props.settings && props.settings.cron_day
          ? parseInt(props.settings.cron_day)
          : 0,
      setting_cron_hour:
        props.settings && props.settings.cron_hour
          ? parseInt(props.settings.cron_hour)
          : 0,
      setting_cron_minute:
        props.settings && props.settings.cron_minute
          ? parseInt(props.settings.cron_minute)
          : 0,
      setting_cron_disabled:
        props.settings && props.settings.cron_disabled
          ? props.settings.cron_disabled
          : false,
      setting_run_fetch:
        props.settings && props.settings.run_fetch
          ? props.settings.run_fetch
          : false,
      setting_cron:
        props.settings && props.settings.cron
          ? props.settings.cron
          : [default_schedule],
      setting_filters:
        props.settings && props.settings.filters ? props.settings.filters : [],
      setting_hash_check: props.settings && props.settings.hash_check ? props.settings.hash_check : false,
      disabled:
        props.pro === false &&
        props.settings &&
        props.settings.import_method === 'schedule',
      saving: false,
      setting_max_row: props.settings.max_row,
      setting_start_row: props.settings.start_row,
    };

    this.onChange = this.onChange.bind(this);
    this.onCronChange = this.onCronChange.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.runNow = this.runNow.bind(this);
    this.save = this.save.bind(this);
    this.onSave = this.onSave.bind(this);
    this.addNewSchedule = this.addNewSchedule.bind(this);
    this.removeSchedule = this.removeSchedule.bind(this);
  }

  onChange(event) {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    console.log(name, value);

    this.setState(
      {
        [name]: value,
      },
      () => {
        this.setState({
          disabled:
            this.props.pro === false &&
            this.state.setting_import_method === 'schedule',
        });
      }
    );
  }

  onCronChange(event, i) {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    // TODO: not store state in array, makes very slow performance
    this.setState({
      setting_cron: [
        ...this.state.setting_cron.slice(0, i),
        Object.assign({}, this.state.setting_cron[i], { [name]: value }),
        ...this.state.setting_cron.slice(i + 1),
      ],
    });
  }

  addNewSchedule() {
    this.setState({ setting_cron: [...this.state.setting_cron, default_schedule] });
  }

  removeSchedule(i) {
    let current = this.state.setting_cron;
    current.splice(i, 1);
    this.setState({ setting_cron: current });
  }

  save(callback = () => { }) {
    const { id } = this.props;
    this.setState({
      saving: true,
    });

    let data = Object.keys(this.state)
      .filter((key) => {
        return key.startsWith('setting_');
      })
      .reduce((obj, key) => {
        obj[key] = this.state[key];
        return obj;
      }, {});
    data.id = id;

    importer.save(data).then(
      () => {
        callback();
      },
      (error) => {
        this.props.onError(error);
        this.setState({
          saving: false,
        });
      }
    );
  }

  onSave() {
    this.save(() => {
      this.setState({
        saving: false,
      });
    });
  }

  runNow() {
    const { id } = this.props;
    this.setState({
      saving: true,
    });

    importer.init(id).then(
      (init_response) => {
        this.setState({
          saving: false,
        });
        const { session } = init_response;
        this.props.onRun(session);
      },
      (error) => {
        this.props.onError(error);
        this.setState({
          saving: false,
        });
      }
    );
  }

  onSubmit() {
    const { id } = this.props;
    this.save(() => {

      // background
      if (this.state.setting_import_method == 'background') {
        importer.init(id).then(
          (init_response) => {
            this.setState({
              saving: false,
            });
          },
          (error) => {
            this.props.onError(error);
            this.setState({
              saving: false,
            });
          }
        );
        return;
      }

      // don't run if its a schedule
      if (this.state.setting_import_method !== 'run') {
        this.setState({
          saving: false,
        });
        return;
      }

      // run importer
      importer.init(id).then(
        (init_response) => {
          this.setState({
            saving: false,
          });
          const { session } = init_response;
          this.props.onRun(session);
        },
        (error) => {
          this.props.onError(error);
          this.setState({
            saving: false,
          });
        }
      );
    });
  }

  render() {
    const {
      setting_import_method,
      disabled,
      saving,
      setting_start_row,
      setting_max_row,
      setting_cron,
      setting_filters,
      setting_run_fetch,
      setting_hash_check
    } = this.state;
    return (
      <React.Fragment>
        <div className="iwp-form">
          <form>
            <p className="iwp-heading iwp-heading--has-tooltip">Run Importer. <a href="https://www.importwp.com/docs/run-import/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>

            <div className="iwp-form__grid">
              <div className="iwp-form__row iwp-form__row--left">
                <FieldLabel
                  label="Start row"
                  field="setting_start_row"
                  id="setting_start_row"
                  tooltip="Set the row you wish to start your import from."
                  display="inline-block"
                />
                <input
                  type="number"
                  className="iwp-form__input"
                  id="setting_start_row"
                  name="setting_start_row"
                  min="0"
                  placeholder="Leave empty to import from the start."
                  onChange={this.onChange}
                  value={setting_start_row}
                />
              </div>

              <div className="iwp-form__row iwp-form__row--right">
                <FieldLabel
                  label="Number of rows"
                  field="setting_max_row"
                  id="setting_max_row"
                  tooltip="Maximum number of rows to import, leave '0' to ignore."
                  display="inline-block"
                />
                <input
                  type="number"
                  className="iwp-form__input"
                  id="setting_max_row"
                  name="setting_max_row"
                  min="0"
                  placeholder="Leave empty to import until the last record."
                  onChange={this.onChange}
                  value={setting_max_row}
                />
              </div>

              <div className="iwp-form__row iwp-form__row--right">
                <label className="iwp-form__label iwp-form__label--switch">
                  <span>Update records only when data has changed.</span>
                  <Switch
                    checked={setting_hash_check}
                    name='setting_hash_check'
                    height={20}
                    width={40}
                    onColor="#22c48f"
                    onChange={checked => {

                      this.onChange({
                        target: {
                          name: 'setting_hash_check',
                          type: 'checkbox',
                          checked
                        }
                      });
                    }}
                  />
                </label>
              </div>
            </div>

            {this.template_settings &&
              this.template_settings.map((field) => (
                <SettingField
                  key={field.id}
                  id={field.id}
                  label={field.label}
                  type={field.type}
                  value={this.state['setting_' + field.id]}
                  onChange={this.onChange}
                />
              ))}

            <ImportFilter
              {...this.props}
              onFilterChange={(filters) => {
                this.setState({
                  setting_filters: filters,
                });
              }}
              filters={setting_filters}
            />

            <div className="iwp-accordion__block iwp-accordion__block--first">
              <div className="iwp-block__handle">
                <label>
                  <input
                    type="radio"
                    name="setting_import_method"
                    value="run"
                    checked={setting_import_method === 'run'}
                    onChange={this.onChange}
                  />{' '}
                  Run Now - <em>Start the import straight away.</em>
                </label>
              </div>
              {setting_import_method === 'run' && (
                <div className="iwp-block__content">
                  <label className="iwp-form__label iwp-form__label--switch">
                    <span>Download new file before import.</span>
                    <Switch
                      checked={setting_run_fetch}
                      name='setting_run_fetch'
                      height={20}
                      width={40}
                      onColor="#22c48f"
                      onChange={checked => {

                        this.onChange({
                          target: {
                            name: 'setting_run_fetch',
                            type: 'checkbox',
                            checked
                          }
                        });
                      }}
                    />
                  </label>
                </div>
              )}
            </div>
            <div className="iwp-accordion__block">
              <div className="iwp-block__handle">
                <label>
                  <input
                    type="radio"
                    name="setting_import_method"
                    value="background"
                    checked={setting_import_method === 'background'}
                    onChange={this.onChange}
                  />{' '}
                  Run in the background - <em>Start the import and let it run in the background.</em>
                </label>
              </div>
              {setting_import_method === 'background' && (
                <div className="iwp-block__content">
                  {React.cloneElement(
                    window.iwp.hooks.applyFilters(
                      'iwp_background_import_method',
                      <UpgradeMessage message="Please upgrade to Import WP Pro v2.11+ to run imports in the background." />
                    ),
                    {
                    },
                    <label className="iwp-form__label iwp-form__label--switch">
                      <span>Download new file before import.</span>
                      <Switch
                        checked={setting_run_fetch}
                        name='setting_run_fetch'
                        height={20}
                        width={40}
                        onColor="#22c48f"
                        onChange={checked => {

                          this.onChange({
                            target: {
                              name: 'setting_run_fetch',
                              type: 'checkbox',
                              checked
                            }
                          });
                        }}
                      />
                    </label>
                  )}
                </div>
              )}
            </div>
            <div className="iwp-accordion__block">
              <div className="iwp-block__handle">
                <label>
                  <input
                    type="radio"
                    name="setting_import_method"
                    value="schedule"
                    checked={setting_import_method === 'schedule'}
                    onChange={this.onChange}
                  />{' '}
                  Schedule - <em>Run the import at a later date.</em>
                </label>
              </div>
              {setting_import_method === 'schedule' && (
                <div className="iwp-block__content">
                  {React.cloneElement(
                    window.iwp.hooks.applyFilters(
                      'iwp_scheduler',
                      <UpgradeMessage message="Please upgrade to Import WP Pro to Schedule this importer." />
                    ),
                    {
                      setting_cron: setting_cron,
                      onCronChange: this.onCronChange,
                      removeSchedule: this.removeSchedule,
                      addNewSchedule: this.addNewSchedule,
                    }
                  )}
                </div>
              )}
            </div>
          </form>
        </div>

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
              {saving
                ? 'Saving'
                : (setting_import_method === 'run' || setting_import_method === 'background')
                  ? 'Save & Run'
                  : 'Save & Schedule'}
            </button>{' '}
            {setting_import_method == 'schedule' && this.props.pro === true && (
              <button
                className="button button-link"
                type="button"
                onClick={this.runNow}
                disabled={disabled}
              >
                Run manually
              </button>
            )}
          </div>
        </div>
      </React.Fragment>
    );
  }
}

ImporterForm.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  template: PropTypes.string,
  settings: PropTypes.object,
  onRun: PropTypes.func,
  onError: PropTypes.func,
  pro: PropTypes.bool,
  templates: PropTypes.array,
};

ImporterForm.defaultProps = {
  complete: () => { },
  template: '',
  settings: {},
  onRun: () => { },
  onError: () => { },
  pro: false,
  templates: [],
};

export default ImporterForm;
