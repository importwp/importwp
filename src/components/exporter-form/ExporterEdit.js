import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useHistory } from 'react-router-dom';

import { exporter } from '../../services/exporter.service';
import FieldLabel from '../field-label/FieldLabel';
import GlobalNotice from '../global-notice/GlobalNotice';
import Modal from '../modal/Modal';
import NoticeList from '../notice-list/NoticeList';
import ProgressBar from '../progress-bar/ProgressBar';
import StatusMessage from '../status-message/StatusMessage';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';
import ExporterFieldSelector from './ExporterFieldSelector';
import ExportFilter from './ExportFilter';

const AJAX_BASE = window.iwp.admin_base;
const AJAX_URL_BASE = window.iwp.ajax_base;

const EXPORT_FILE_TYPES = [
  { id: 'csv', label: 'CSV' },
  { id: 'xml', label: 'XML' },
  { id: 'json', label: 'JSON' },
];
const EXPORTER_FIELDS = window.iwp.export_fields;

const default_schedule = {
  setting_cron_disabled: false,
  setting_cron_schedule: 'month',
  setting_cron_day: 0,
  setting_cron_hour: 0,
  setting_cron_minute: 0,
};

const ExporterEdit = ({ id, pro = false }) => {
  const [name, setName] = useState('');
  const [type, setType] = useState('');
  const [fileType, setFileType] = useState('');
  const [fields, setFields] = useState([]);
  const [disabled, setDisabled] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [notices, setNotices] = useState([]);
  const [running, setRunning] = useState(false);
  const [progress, setProgress] = useState(0);
  const [status, setStatus] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [modalTitle, setModalTitle] = useState('');
  const [filters, setFilters] = useState([]);
  const [uniqueIdentifier, setUniqueIdentifier] = useState('');
  const [exportMethod, setExportMethod] = useState('run');
  const [cronSettings, setCronSettings] = useState([default_schedule]);
  const [activeSession, setActiveSession] = useState('');

  const history = useHistory();

  const didMount = useRef(-1);
  const runnerXHR = useRef(null);
  const statusXHR = useRef(null);

  const fieldOptions = [...EXPORTER_FIELDS];

  const fileTypes = [...EXPORT_FILE_TYPES];
  const exporterId = parseInt(id) || 0;

  const logError = (error) => {
    let message = error;
    if (error.hasOwnProperty('statusText') && error.hasOwnProperty('status')) {
      message = `The following error has occured: ${error.statusText}, Code: ${error.status}`;
    }

    setNotices([
      ...notices,
      { message: message, type: 'error', dismissible: true },
    ]);
  };

  const activeType = useMemo(() => {
    const tmp = fieldOptions.find((option) => option.id === type);
    return tmp;
  }, [fieldOptions, type]);

  const onSave = async (e) => {
    e.preventDefault();
    const saved = await save();
  };

  const onSaveAndRun = async (e) => {
    const saved = await save();
    if (saved && exportMethod === 'run') {
      run();
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      const data = await exporter.save({
        id,
        name,
        type,
        file_type: fileType,
        unique_identifier: uniqueIdentifier,
        fields: fields,
        filters,
        export_method: exportMethod,
        cron: cronSettings,
      });

      if (didMount.current !== data.id) {
        history.push(AJAX_BASE + '&edit-exporter=' + data.id);
      }
      setSaving(false);
      return true;
    } catch (e) {
      logError(e);
    }

    setSaving(false);
    return false;
  };

  const run = () => {
    setModalTitle('Exporting');
    setShowModal(true);
    setRunning(true);
    setProgress(0);
    setStatus(null);

    if (statusXHR.current !== null) {
      statusXHR.current.abort();
      statusXHR.current = null;
    }

    exporter.init(id).then(
      (init_response) => {
        const { session } = init_response;
        setActiveSession(session);

        runnerXHR.current = exporter.run(id, session);
        runnerXHR.current.request.subscribe(
          (response) => {
            if (response.status === 'running' || response.status === 'timeout') {
              setProgress(((response.progress.export.current_row / (response.progress.export.end - response.progress.export.start)) * 100).toFixed());
            } else if (response.status == 'complete') {
              setModalTitle('Export Complete');
              setProgress(100);
              setStatus(response);
              setRunning(false);
              runnerXHR.current.abort();
              getStatus();
            }
          },
          (error) => {
            logError(error);
            runnerXHR.current.abort();
            getStatus();
          }
        );
      },
      (error) => {
        logError(error);
        getStatus();
      }
    );
  };

  const getStatus = () => {
    console.log('getStatus init');
    statusXHR.current = exporter.status([id]);
    statusXHR.current.request.subscribe(
      (response) => {
        const tmpStatus = response.find((item) => item.exporter === id);
        if (tmpStatus) {
          setStatus(tmpStatus);
          setProgress(tmpStatus.progress.toFixed());
        }
      },
      (e) => {
        // ignore abort errors
        if (e.status > 0) {
          logError(e);
        }
      }
    );
  };

  // useEffect(() => {
  //   if (running) {
  //     getStatus();
  //   } else if (statusXHR.current !== null) {
  //     statusXHR.current.abort();
  //   }
  // }, [running]);

  useEffect(() => {
    if (exportMethod === 'schedule' && !pro) {
      setDisabled(true);
      return;
    }

    if (
      name.length &&
      type.length &&
      (exporterId <= 0 || (fileType.length && uniqueIdentifier.length))
    ) {
      setDisabled(false);
    } else {
      setDisabled(true);
    }
  }, [name, type, fileType, uniqueIdentifier, exportMethod]);

  useEffect(() => {
    if (didMount.current !== id) {
      // id changed
      if (id > 0) {
        setLoading(true);
        exporter
          .get(id)
          .then((data) => {
            setName(data.name);
            setFields(data.fields);
            setFileType(data.file_type);
            setType(data.type);
            setFilters(data.filters);
            setUniqueIdentifier(data.unique_identifier);
            setExportMethod(data.export_method ?? 'run');
            setCronSettings(data.cron ?? []);
            setLoading(false);
          })
          .catch((e) => {
            logError(e);
            setLoading(false);
          });
      } else {
        setLoading(false);
      }

      didMount.current = id;
    }
  }, [id]);

  useEffect(() => {
    if (statusXHR.current == null) {
      getStatus();
    }

    return () => {
      if (statusXHR.current !== null) {
        statusXHR.current.abort();
      }
    };
  }, []);

  let noticeList;
  if (loading) {
    return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
  } else {
    noticeList = (
      <NoticeList
        notices={notices}
        onDismiss={(i) => {
          setNotices(
            notices.map((item, item_i) =>
              item_i === i ? { ...item, dismissed: true } : item
            )
          );
        }}
      />
    );
  }

  const generateUniqueIdOptionList = (fields) => {
    return fields?.fields ? fields.fields : fields;
  };

  const onCronChange = (event, i) => {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    // TODO: not store state in array, makes very slow performance
    setCronSettings([
      ...cronSettings.slice(0, i),
      Object.assign({}, cronSettings[i], { [name]: value }),
      ...cronSettings.slice(i + 1),
    ]);
  };

  const addNewSchedule = () => {
    setCronSettings([...cronSettings, default_schedule]);
  };

  const removeSchedule = (i) => {
    setCronSettings([
      ...cronSettings.slice(0, i),
      ...cronSettings.slice(i + 1),
    ]);
  };

  return (
    <>
      <GlobalNotice />
      <Modal
        title={modalTitle}
        show={showModal}
        onClose={() => setShowModal(false)}
      >
        <ProgressBar progress={progress} text={progress + '%'} />

        {status?.status === 'complete' && activeSession === status?.id && (
          <>
            <a
              href={`${AJAX_BASE}&exporter=${id}&download=${status.file}`}
              target="_blank"
              className="button button-secondary"
            >
              Download
            </a>{' '}
            <a
              href={`${AJAX_BASE}&exporter=${id}&download=${status.file}`}
              target="_blank"
              className="button button-primary"
              onClick={() => setShowModal(false)}
            >
              Download & close
            </a>
          </>
        )}
      </Modal>

      {id > 0 && (
        <p className="iwp-importer-header" style={{ marginBottom: '20px' }}>
          {name ? name : 'Untitled Exporter'}
          <small>
            Exporting <strong>{type}</strong> to <strong>{fileType}</strong>.
          </small>
        </p>
      )}

      {id > 0 &&
        status &&
        status?.version === 2 &&
        (status.status === 'running' || status?.cron) && (
          <NoticeList
            notices={[
              {
                message: (
                  <React.Fragment>
                    <StatusMessage status={status} />
                  </React.Fragment>
                ),
                type: 'warn',
              },
            ]}
          />
        )}

      {noticeList}

      <form>
        <div className="iwp-form">
          {!id && <p className="iwp-heading">Create Exporter</p>}

          <div className="iwp-form__row ">
            <FieldLabel
              id="name"
              field="name"
              label="Name"
              tooltip="Enter the name of the exporter, the name is only used to help find your exporter."
              display="inline-block"
            />
            <input
              id="name"
              name="name"
              type="text"
              className="iwp-form__input"
              onChange={(e) => setName(e.target.value)}
              value={name}
              placeholder="exporter name"
            />
          </div>

          <div className="iwp-form__row ">
            <FieldLabel
              id="type"
              field="type"
              label="Exporting"
              display="inline-block"
            />
            <select
              className="iwp-form__input"
              name="type"
              onChange={(e) => {
                setType(e.target.value);
                setFields([]);
              }}
              value={type}
            >
              <option value="">Choose option</option>
              {EXPORTER_FIELDS.map((option) => (
                <option key={option.id} value={option.id}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          {exporterId > 0 && (
            <>
              {typeof activeType !== 'undefined' && (
                <>
                  <div className="iwp-form__row">
                    <FieldLabel
                      id="unique_identifier"
                      field="unique_identifier"
                      label="Unique Identifier"
                      display="inline-block"
                      tooltip="Select a column to uniquely identify each record in your file with WordPress object."
                    />
                    <select
                      className="iwp-form__input"
                      name="fields"
                      onChange={(e) => setUniqueIdentifier(e.target.value)}
                      value={uniqueIdentifier}
                    >
                      <option value="">Select unique identifier</option>
                      {generateUniqueIdOptionList(activeType.fields).map(
                        (option) => (
                          <option key={option} value={option}>
                            {option}
                          </option>
                        )
                      )}
                    </select>
                  </div>
                  <div className="iwp-form__row">
                    <FieldLabel
                      id="file_type"
                      field="file_type"
                      label="File Type"
                      display="inline-block"
                    />
                    <select
                      name="file_type"
                      id=""
                      className="iwp-form__input"
                      onChange={(e) => setFileType(e.target.value)}
                      value={fileType}
                    >
                      <option value="">Choose option</option>
                      {fileTypes.map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  {/* {fileType && (
                    <>
                      <div className="iwp-form__row">
                        <FieldLabel
                          id="fields"
                          field="fields"
                          label="Fields to export"
                          display="inline-block"
                        />
                        <SortableSelect
                          axis="xy"
                          onSortEnd={onSortEnd}
                          distance={4}
                          getHelperDimensions={({ node }) =>
                            node.getBoundingClientRect()
                          }
                          isMulti
                          options={activeType.fields.map((option) => {
                            return { value: option, label: option };
                          })}
                          value={fields}
                          onChange={onChange}
                          components={{
                            MultiValue: SortableMultiValue,
                          }}
                          closeMenuOnSelect={false}
                        />
                      </div>
                    </>
                  )} */}

                  {fileType && (
                    <ExporterFieldSelector
                      fileType={fileType}
                      activeType={activeType}
                      setFields={setFields}
                      fields={fields}
                    />
                  )}
                </>
              )}

              <div className="iwp-form__row">
                <FieldLabel
                  // id="fields"
                  // field="fields"
                  label="Filter Records"
                  display="inline-block"
                />
                <ExportFilter
                  filters={filters}
                  onFilterChange={(data) => setFilters(data)}
                />
              </div>

              <div className="iwp-accordion__block iwp-accordion__block--first">
                <div className="iwp-block__handle">
                  <label>
                    <input
                      type="radio"
                      name="export_method"
                      value="run"
                      checked={exportMethod === 'run'}
                      onChange={(event) => setExportMethod(event.target.value)}
                    />{' '}
                    Run Now - <em>Start the export straight away.</em>
                  </label>
                </div>
              </div>
              <div className="iwp-accordion__block">
                <div className="iwp-block__handle">
                  <label>
                    <input
                      type="radio"
                      name="export_method"
                      value="schedule"
                      checked={exportMethod === 'schedule'}
                      onChange={(event) => setExportMethod(event.target.value)}
                    />{' '}
                    Schedule - <em>Run the export at a later date.</em>
                  </label>
                </div>
                {exportMethod === 'schedule' && (
                  <div className="iwp-block__content">
                    {React.cloneElement(
                      window.iwp.hooks.applyFilters(
                        'iwp_exporter_scheduler',
                        <UpgradeMessage message="Please upgrade to Import WP Pro to Schedule this exporter." />
                      ),
                      {
                        setting_cron: cronSettings,
                        onCronChange,
                        removeSchedule,
                        addNewSchedule,
                      }
                    )}
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      </form>
      <div className="iwp-form__actions">
        <div className="iwp-buttons">
          <button
            className={'button button-' + (id > 0 ? 'secondary' : 'primary')}
            type="button"
            onClick={onSave}
            disabled={disabled}
          >
            {saving && <span className="spinner is-active"></span>}
            {saving ? 'Saving' : id > 0 ? 'Save' : 'Create Exporter'}
          </button>{' '}
          {id > 0 && (
            <button
              className="button button-primary"
              type="button"
              onClick={onSaveAndRun}
              disabled={disabled}
            >
              {saving && <span className="spinner is-active"></span>}
              {saving
                ? 'Saving'
                : exportMethod == 'run'
                  ? 'Save & Run'
                  : 'Save & Schedule'}
            </button>
          )}
        </div>
      </div>
    </>
  );
};

export default ExporterEdit;
