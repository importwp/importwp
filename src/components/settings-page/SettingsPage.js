import React from 'react';
import { Link, withRouter } from 'react-router-dom';
import PropTypes from 'prop-types';
import Switch from 'react-switch';
import qs from 'qs';
import './SettingsPage.scss';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import ToolsPage from '../tools-page/ToolsPage';
import FieldLabel from '../field-label/FieldLabel';
import GlobalNotice from '../global-notice/GlobalNotice';

class SettingsPage extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      loading: true,
      setting_debug: false,
      setting_cleanup: false,
      setting_file_rotation: 5,
      setting_log_rotation: -1,
      setting_timeout: 30,
      saving: false,
      disabled: false,
      compatibility: {}
    };

    this.onSwitchChange = this.onSwitchChange.bind(this);
    this.onSave = this.onSave.bind(this);
    this.onSaveCompatibility = this.onSaveCompatibility.bind(this);
  }

  componentDidMount() {
    this.settingsSubject = importer.getSettings().subscribe({
      next: (data) => {
        let settings = {};
        Object.keys(data).forEach(
          (setting) => (settings['setting_' + setting] = data[setting])
        );
        this.setState(settings);
        this.setState({ loading: false });
      },
      error: (error) => {
        this.setState({ loading: false });
      },
    });

    this.compatibilitySubject = importer.getCompatibility().subscribe({
      next: (data) => {
        let compatibility = {};
        Object.keys(data).forEach(
          (setting) => (compatibility[setting] = data[setting])
        );
        this.setState({ loading: false, compatibility });
      },
      error: (error) => {
        this.setState({ loading: false });
      },
    });
  }

  componentWillUnmount() {
    this.settingsSubject.unsubscribe();
  }

  getActiveSection() {
    const values = qs.parse(this.props.location.search);
    if (typeof values.section !== 'undefined') {
      const { section } = values;
      if (section === 'info') {
        return 'info';
      } else if (section === 'import-export') {
        return 'import-export';
      } else if (section === 'compat') {
        return 'compat';
      }
    }
    return 'general';
  }

  onSwitchChange(name, checked) {
    this.setState(
      {
        [name]: checked,
      },
      this.onSave
    );
  }

  onSave() {
    this.setState({
      saving: true,
    });
    importer
      .saveSettings({
        debug: this.state.setting_debug,
        cleanup: this.state.setting_cleanup,
        file_rotation: this.state.setting_file_rotation,
        log_rotation: this.state.setting_log_rotation,
        timeout: this.state.setting_timeout,
      })
      .then(() => {
        this.setState({
          saving: false,
        });
      });
  }

  onSaveCompatibility() {

    const enabled = Object.keys(this.state.compatibility).reduce((prev, cur) => {

      return this.state.compatibility[cur].enabled === 'yes' ? [
        ...prev,
        cur
      ] : prev;

    }, []);

    this.setState({
      saving: true,
    });
    importer
      .saveCompatibility({ plugins: enabled })
      .then(() => {
        this.setState({
          saving: false,
        });
      });
  }

  render() {
    const { saving, disabled, loading } = this.state;
    const switch_height = 20;
    const switch_width = 40;
    const base = this.props.location.pathname + '?page=importwp&tab=settings';
    const active = this.getActiveSection();

    if (loading === true) {
      return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
    }

    return (
      <div>
        <GlobalNotice />
        <ul className="iwp-tabs iwp-tabs--center iwp-tabs--pills">
          <li
            className={
              'iwp-tabs__tab ' +
              (active === 'general' ? 'iwp-tabs__tab--active' : '')
            }
          >
            <Link to={base}>General Settings</Link>
          </li>
          <li
            className={
              'iwp-tabs__tab ' +
              (active === 'compat' ? 'iwp-tabs__tab--active' : '')
            }
          >
            <Link to={base + '&section=compat'}>Compatibility</Link>
          </li>
          <li
            className={
              'iwp-tabs__tab ' +
              (active === 'import-export' ? 'iwp-tabs__tab--active' : '')
            }
          >
            <Link to={base + '&section=import-export'}>Import / Export</Link>
          </li>
        </ul>

        {active === 'import-export' && <ToolsPage />}

        {active === 'general' && (
          <React.Fragment>
            <div className="iwp-form iwp-form--mb">
              <p className="iwp-heading">General Settings</p>

              <div className="iwp-form__row iwp-form__row--small">
                <label className="iwp-form__label iwp-form__label--switch">
                  <span>Enable Debug Mode.</span>
                  <Switch
                    checked={this.state.setting_debug}
                    height={switch_height}
                    width={switch_width}
                    onColor="#22c48f"
                    onChange={(checked) =>
                      this.onSwitchChange('setting_debug', checked)
                    }
                  />
                </label>
              </div>

              <div className="iwp-form__row iwp-form__row--small">
                <label className="iwp-form__label iwp-form__label--switch">
                  <span>Cleanup plugin data on uninstall.</span>
                  <Switch
                    checked={this.state.setting_cleanup}
                    height={switch_height}
                    width={switch_width}
                    onColor="#22c48f"
                    onChange={(checked) =>
                      this.onSwitchChange('setting_cleanup', checked)
                    }
                  />
                </label>
              </div>

              <p className="iwp-heading">Import Settings</p>
              <div className="iwp-form__row iwp-form__row--small iwp-form__row--inline">
                <FieldLabel
                  label="File Rotation"
                  id="file_rotation"
                  field="file_rotation"
                  tooltip="The maximum number of files to be kept per scheduled importer, files will be deleted at the end of an import (-1 to keep all)."
                />
                <input
                  type="number"
                  name="file_rotation"
                  onChange={(e) => {
                    this.setState({
                      ['setting_' + e.target.name]: e.target.value,
                    });
                  }}
                  value={this.state.setting_file_rotation}
                  min={-1}
                  step={1}
                />
              </div>
              <div className="iwp-form__row iwp-form__row--small iwp-form__row--inline">
                <FieldLabel
                  label="Log Rotation"
                  id="log_rotation"
                  field="log_rotation"
                  tooltip="The maximum number of logs to be kept per scheduled importer, logs will be deleted at the end of an import (-1 to keep all)."
                />
                <input
                  type="number"
                  name="log_rotation"
                  onChange={(e) => {
                    this.setState({
                      ['setting_' + e.target.name]: e.target.value,
                    });
                  }}
                  value={this.state.setting_log_rotation}
                  min={-1}
                  step={1}
                />
              </div>
              <div className="iwp-form__row iwp-form__row--small iwp-form__row--inline">
                <FieldLabel
                  label="Timeout"
                  id="timeout"
                  field="timeout"
                  tooltip="Maximum time in seconds that an importer can run for."
                />
                <input
                  type="number"
                  name="timeout"
                  onChange={(e) => {
                    this.setState({
                      ['setting_' + e.target.name]: e.target.value,
                    });
                  }}
                  value={this.state.setting_timeout}
                  min={-1}
                  step={1}
                />
              </div>
            </div>

            <div className="iwp-form__actions">
              <div className="iwp-buttons">
                <button
                  className="button button-primary"
                  type="button"
                  onClick={this.onSave}
                  disabled={disabled}
                >
                  {saving && <span className="spinner is-active"></span>}
                  {saving ? 'Saving' : ' Save Settings'}
                </button>
              </div>
            </div>
          </React.Fragment>
        )}

        {active === 'compat' && <>
          <div className="iwp-form iwp-form--mb">
            <p className="iwp-heading">Compatibility Settings</p>

            <p>Select which plugins should be disabled during the import process.</p>

            {this.state.loading ? (
              <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />
            ) :

              <div style={{
                background: '#f9f9f9',
                padding: '10px',
                border: '1px solid #efefef'
              }}>

                {Object.keys(this.state.compatibility).length === 0 && <p style={{ padding: '0', margin: '0' }}>No plugins have been found</p>}

                {Object.keys(this.state.compatibility).map(plugin_id => <label style={{
                  display: 'block',
                  marginBottom: '5px'
                }}>
                  <input type="checkbox" checked={this.state.compatibility[plugin_id].enabled === 'yes'} onChange={(e) => {
                    this.setState({
                      compatibility: {
                        ...this.state.compatibility,
                        [plugin_id]: {
                          ...this.state.compatibility[plugin_id],
                          enabled: this.state.compatibility[plugin_id].enabled === 'yes' ? 'no' : 'yes'
                        }
                      }
                    })
                  }} />
                  {this.state.compatibility[plugin_id].name}
                </label>)}

              </div>
            }

          </div>

          <div className="iwp-form__actions">
            <div className="iwp-buttons">
              <button
                className="button button-primary"
                type="button"
                onClick={this.onSaveCompatibility}
                disabled={disabled}
              >
                {saving && <span className="spinner is-active"></span>}
                {saving ? 'Saving' : ' Save Settings'}
              </button>
            </div>
          </div>
        </>}
      </div>
    );
  }
}

SettingsPage.propTypes = {
  location: PropTypes.object,
};

// export default SettingsPage;

export default withRouter(SettingsPage);
