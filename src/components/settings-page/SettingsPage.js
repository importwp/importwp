import React, { useCallback, useEffect, useRef, useState } from 'react';
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
import { logAjaxError } from '../../util/ajax-error';
import { setDebug } from '../../util/debug';

function SettingsPage(props) {
  const [loading, setLoading] = useState(true);
  const [setting_debug, setSettingDebug] = useState(false);
  const [setting_cleanup, setSettingCleanup] = useState(false);
  const [setting_file_rotation, setSettingFileRotation] = useState(5);
  const [setting_log_rotation, setSettingLogRotation] = useState(-1);
  const [setting_timeout, setSettingTimeout] = useState(30);
  const [saving, setSaving] = useState(false);
  const [disabled, setDisabled] = useState(false);
  const [compatibility, setCompatibility] = useState({});
  const [notices, setNotices] = useState([]);

  const settingsSubjectRef = useRef(null);
  const compatibilitySubjectRef = useRef(null);
  const savingRef = useRef(false);

  const logError = useCallback((error) => {
    const message = logAjaxError(error, 'SettingsPage');
    setNotices((prevNotices) => [
      ...prevNotices,
      { message, type: 'error', dismissible: true },
    ]);
  }, []);

  const saveSettings = useCallback(
    (settings) => {
      if (savingRef.current) {
        return;
      }

      savingRef.current = true;
      setSaving(true);
      importer
        .saveSettings(settings)
        .then(() => {
          setDebug(settings.debug);
          savingRef.current = false;
          setSaving(false);
        })
        .catch((error) => {
          savingRef.current = false;
          setSaving(false);
          logError(error);
        });
    },
    [logError]
  );

  const onSave = useCallback(() => {
    saveSettings({
      debug: setting_debug,
      cleanup: setting_cleanup,
      file_rotation: setting_file_rotation,
      log_rotation: setting_log_rotation,
      timeout: setting_timeout,
    });
  }, [
    saveSettings,
    setting_debug,
    setting_cleanup,
    setting_file_rotation,
    setting_log_rotation,
    setting_timeout,
  ]);

  const onSaveCompatibility = useCallback(() => {
    const enabled = Object.keys(compatibility).reduce((prev, cur) => {
      return compatibility[cur].enabled === 'yes' ? [...prev, cur] : prev;
    }, []);

    if (savingRef.current) {
      return;
    }

    savingRef.current = true;
    setSaving(true);
    importer
      .saveCompatibility({ plugins: enabled })
      .then(() => {
        savingRef.current = false;
        setSaving(false);
      })
      .catch((error) => {
        savingRef.current = false;
        setSaving(false);
        logError(error);
      });
  }, [compatibility, logError]);

  useEffect(() => {
    settingsSubjectRef.current = importer.getSettings().subscribe({
      next: (data) => {
        Object.keys(data).forEach((setting) => {
          const value = data[setting];
          switch (setting) {
            case 'debug':
              setSettingDebug(value);
              break;
            case 'cleanup':
              setSettingCleanup(value);
              break;
            case 'file_rotation':
              setSettingFileRotation(value);
              break;
            case 'log_rotation':
              setSettingLogRotation(value);
              break;
            case 'timeout':
              setSettingTimeout(value);
              break;
            default:
              break;
          }
        });
        setLoading(false);
      },
      error: (error) => {
        setLoading(false);
        logError(error);
      },
    });

    compatibilitySubjectRef.current = importer.getCompatibility().subscribe({
      next: (data) => {
        let nextCompatibility = {};
        Object.keys(data).forEach(
          (setting) => (nextCompatibility[setting] = data[setting])
        );
        setLoading(false);
        setCompatibility(nextCompatibility);
      },
      error: (error) => {
        setLoading(false);
        logError(error);
      },
    });

    return () => {
      settingsSubjectRef.current.unsubscribe();
    };
  }, [logError]);

  const onSwitchChange = (name, checked) => {
    if (name === 'setting_debug') {
      setSettingDebug(checked);
    } else if (name === 'setting_cleanup') {
      setSettingCleanup(checked);
    }

    saveSettings({
      debug: name === 'setting_debug' ? checked : setting_debug,
      cleanup: name === 'setting_cleanup' ? checked : setting_cleanup,
      file_rotation: setting_file_rotation,
      log_rotation: setting_log_rotation,
      timeout: setting_timeout,
    });
  };

  const getActiveSection = () => {
    const values = qs.parse(props.location.search);
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
  };

  const switch_height = 20;
  const switch_width = 40;
  const base = props.location.pathname + '?page=importwp&tab=settings';
  const active = getActiveSection();

  if (loading === true) {
    return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
  }

  return (
    <div>
      <GlobalNotice />
      <NoticeList
        notices={notices}
        onDismiss={(i) => {
          setNotices((prevNotices) =>
            prevNotices.map((item, item_i) =>
              item_i === i ? { ...item, dismissed: true } : item
            )
          );
        }}
      />
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
                  checked={setting_debug}
                  height={switch_height}
                  width={switch_width}
                  onColor="#22c48f"
                  onChange={(checked) =>
                    onSwitchChange('setting_debug', checked)
                  }
                />
              </label>
            </div>

            <div className="iwp-form__row iwp-form__row--small">
              <label className="iwp-form__label iwp-form__label--switch">
                <span>Cleanup plugin data on uninstall.</span>
                <Switch
                  checked={setting_cleanup}
                  height={switch_height}
                  width={switch_width}
                  onColor="#22c48f"
                  onChange={(checked) =>
                    onSwitchChange('setting_cleanup', checked)
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
                  setSettingFileRotation(e.target.value);
                }}
                value={setting_file_rotation}
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
                  setSettingLogRotation(e.target.value);
                }}
                value={setting_log_rotation}
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
                  setSettingTimeout(e.target.value);
                }}
                value={setting_timeout}
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
                onClick={onSave}
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

          {loading ? (
            <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />
          ) :

            <div style={{
              background: '#f9f9f9',
              padding: '10px',
              border: '1px solid #efefef'
            }}>

              {Object.keys(compatibility).length === 0 && <p style={{ padding: '0', margin: '0' }}>No plugins have been found</p>}

              {Object.keys(compatibility).map(plugin_id => <label style={{
                display: 'block',
                marginBottom: '5px'
              }}>
                <input type="checkbox" checked={compatibility[plugin_id].enabled === 'yes'} onChange={(e) => {
                  setCompatibility((prevCompatibility) => ({
                    ...prevCompatibility,
                    [plugin_id]: {
                      ...prevCompatibility[plugin_id],
                      enabled: prevCompatibility[plugin_id].enabled === 'yes' ? 'no' : 'yes'
                    }
                  }));
                }} />
                {compatibility[plugin_id].name}
              </label>)}

            </div>
          }

        </div>

        <div className="iwp-form__actions">
          <div className="iwp-buttons">
            <button
              className="button button-primary"
              type="button"
              onClick={onSaveCompatibility}
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

SettingsPage.propTypes = {
  location: PropTypes.object,
};

export default withRouter(SettingsPage);
