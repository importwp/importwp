import React, { useCallback, useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { withRouter } from 'react-router';
import qs from 'qs';
import { compose } from '@reduxjs/toolkit';
import { connect } from 'react-redux';
import { setImporter } from '../../features/importer/importerSlice';

import EditSteps from '../edit-steps/EditSteps';
import SetupForm from '../setup-form/SetupForm';
import DatasourceForm from '../datasource-form/DatasourceForm';
import PreviewXmlForm from '../preview-xml-form/PreviewXmlForm';
import PreviewCsvForm from '../preview-csv-form/PreviewCsvForm';
import PreviewJsonForm from '../preview-json-form/PreviewJsonForm';
import TemplateForm from '../template-form/TemplateForm';

import './EditPage.scss';
import { importer } from '../../services/importer.service';
import PermissionForm from '../permission-form/PermissionForm';
import ImporterForm from '../importer-form/ImporterForm';
import NoticeList from '../notice-list/NoticeList';
import ImportRunner from '../import-runner/ImportRunner';
import StatusMessage from '../status-message/StatusMessage';
import ImporterLogs from '../importer-logs/ImporterLogs';

import ErrorBoundary from '../error-boundary/ErrorBoundary';
import ImporterDebug from '../importer-debug/ImporterDebug';
import GlobalNotice from '../global-notice/GlobalNotice';
import PreviewForm from '../preview-form/PreviewForm';
import { logAjaxError } from '../../util/ajax-error';
import { debugLog } from '../../util/debug';
import { computeMaxStep, getStepFromSearch, shouldPushStepUrl } from './step';

const AJAX_BASE = window.iwp.admin_base;

const resetImporter = {
  parser: null,
  file: null,
  files: {},
  permissions: {},
  settings: {},
};

const EditPage = ({
  id = null,
  location,
  history,
  pro = false,
  templates = [],
  setImporter: setImporterAction,
}) => {
  const initialForm = id === null ? 'add' : 'edit';
  const initialStep = id === null ? -1 : 0;

  const [step, setStep] = useState(initialStep);
  const [form, setForm] = useState(initialForm);
  const [maxStep, setMaxStepState] = useState(initialStep);
  const [init, setInit] = useState(false);
  const [importerState, setImporterState] = useState(resetImporter);
  const [datasource_type, setDatasourceType] = useState(null);
  const [datasource_settings, setDatasourceSettings] = useState({});
  const [notices, setNotices] = useState([]);
  const [run_importer, setRunImporter] = useState(null);
  const [status, setStatus] = useState(null);
  const [show_debug, setShowDebug] = useState(false);

  const statusXHR = useRef(null);
  const importerSubject = useRef(null);
  const statusSubject = useRef(null);
  const getImporterRef = useRef(null);

  const logError = useCallback((error) => {
    const message = logAjaxError(error, 'EditPage');
    setNotices((current) => [
      ...current,
      { message: message, type: 'error', dismissible: true },
    ]);
    window.scrollTo(0, 0);
  }, []);

  const getActiveStep = useCallback((maxStepValue) => {
    setStep(getStepFromSearch(location.search, maxStepValue));
  }, [location.search]);

  const setMaxStep = useCallback((importerData) => {
    const max = computeMaxStep(importerData);
    setMaxStepState(max);
    return max;
  }, []);

  const getStatus = useCallback(() => {
    statusXHR.current = importer.status([id]);
    statusSubject.current = statusXHR.current.request.subscribe(
      (response) => {
        setStatus(
          response.find((item) =>
            item?.version == 2 ? item.importer == id : item.id === id
          )
        );
      },
      () => {}
    );
  }, [id]);

  const getImporter = useCallback(() => {
    if (importerSubject.current !== null) {
      importerSubject.current.unsubscribe();
    }

    importerSubject.current = importer.getAndSubscribe(id).subscribe({
      next: (data) => {
        if (data !== null) {
          setInit(true);
          setImporterState(data);
          setDatasourceType(data.datasource.type);
          setDatasourceSettings(data.datasource.settings);
          setImporterAction(data);
          const max = setMaxStep(data);
          getActiveStep(max);

          if (statusXHR.current === null) {
            getStatus();
          }
        }
      },
      error: (error) => {
        logError(error);
      },
    });
  }, [getActiveStep, getStatus, id, logError, setImporterAction, setMaxStep]);

  const nextStep = useCallback(() => {
    setStep((current) => {
      let next = current + 1;
      setMaxStepState((currentMax) => (next > currentMax ? next : currentMax));
      if (next > 4) {
        next = 0;
      }
      return next;
    });
  }, []);

  const gotoStep = useCallback((next) => {
    setStep(next);
  }, []);

  const createImporter = useCallback((nextId) => {
    if (id === null) {
      history.push(AJAX_BASE + '&edit=' + nextId);
    }
    nextStep();
  }, [history, id, nextStep]);

  const runImport = useCallback((session) => {
    if (statusXHR.current !== null) {
      statusXHR.current.abort();
      statusXHR.current = null;
    }
    setRunImporter(session);
  }, []);

  getImporterRef.current = getImporter;

  useEffect(() => {
    const nextId = parseInt(id, 10) || 0;
    debugLog('EditPage load', nextId);

    if (nextId > 0) {
      getImporterRef.current();
    } else {
      setForm('add');
      setStep(-1);
      setMaxStepState(-1);
      setInit(true);
      setNotices([]);
      setImporterState(resetImporter);
    }

    return () => {
      if (statusXHR.current) {
        statusXHR.current.abort();
        statusXHR.current = null;
      }
      importer.abort();
      if (importerSubject.current !== null) {
        importerSubject.current.unsubscribe();
        importerSubject.current = null;
      }
      if (statusSubject.current !== null) {
        statusSubject.current.unsubscribe();
        statusSubject.current = null;
      }
    };
  }, [id]);

  useEffect(() => {
    const values = qs.parse(location.search, { ignoreQueryPrefix: true });
    const currentUrlStep = values.step ? parseInt(values.step, 10) : null;

    if (!shouldPushStepUrl({ init, id, currentUrlStep, step })) {
      return;
    }

    let url = AJAX_BASE + '&edit=' + id + '&step=' + step;
    if (values.log && step === 5) {
      url += '&log=' + values.log;
    }
    history.push(url);
  }, [history, id, init, location.search, step]);

  const { template, parser, file, files, enabled, permissions } = importerState;
  const settings = file ? file.settings : null;

  if (init === false) {
    return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
  }

  const general_settings = importerState.settings;

  return (
    <React.Fragment>
        <GlobalNotice />

        {run_importer !== null && (
          <ImportRunner
            id={id}
            session={run_importer}
            status={status}
            onComplete={() => {
              setRunImporter(null); setStatus(null);

              if (statusXHR.current === null) {
                getStatus();
              }
            }}
          />
        )}

        <EditSteps
          id={id}
          step={step}
          form={form}
          gotoStep={gotoStep}
          maxStep={maxStep}
          importer={importerState}
          onError={logError}
        />

        {id > 0 &&
          status &&
          status?.version === 2 &&
          (status.status === 'running' || status.status === 'processing' || status.status === 'timeout' || status?.cron) && (
            <NoticeList
              notices={[
                {
                  message: (
                    <React.Fragment>
                      <StatusMessage status={status} />
                      {(status?.status === 'running' || status?.status === 'processing' || status.status === 'timeout') && (
                        <div className="iwp-notice__actions">
                          {!status.hasOwnProperty('cron') && (
                            <button
                              type="button"
                              className="button-link-continue"
                              onClick={() => {
                                // TODO: how do we resume
                                if (status?.id) {
                                  runImport(status.id);
                                }
                              }}
                            >
                              Continue
                            </button>
                          )}
                          <button
                            type="button"
                            className="button-link-delete"
                            onClick={() => {
                              importer.stop(id);
                            }}
                            style={{
                              marginRight: '10px',
                            }}
                          >
                            Cancel
                          </button>
                        </div>
                      )}
                    </React.Fragment>
                  ),
                  type: 'warn',
                },
              ]}
            />
          )}

        <NoticeList
          notices={notices}
          onDismiss={(i) => {
            setNotices((current) =>
              current.map((notice, index) =>
                index === i ? { ...notice, dismissed: true } : notice
              )
            );
          }}
        />

        {step === -1 && (
          <ErrorBoundary>
            <SetupForm
              id={id}
              complete={createImporter}
              template={template}
              onError={logError}
              templates={templates}
            />
          </ErrorBoundary>
        )}
        {step === 0 && (
          <ErrorBoundary>
            <DatasourceForm
              id={id}
              complete={nextStep}
              parser={parser}
              file={file ? file.id : null}
              files={files}
              datasource={datasource_type}
              settings={datasource_settings}
              onError={logError}
            />
          </ErrorBoundary>
        )}
        {step === 1 && (
          <ErrorBoundary>
            {parser === 'xml' && (
              <PreviewXmlForm
                id={id}
                complete={nextStep}
                settings={settings}
                onError={logError}
              />
            )}
            {parser === 'csv' && (
              <PreviewCsvForm
                id={id}
                complete={nextStep}
                settings={settings}
                onError={logError}
              />
            )}
            {parser === 'json' && (
              <PreviewJsonForm
                id={id}
                complete={nextStep}
                settings={settings}
                onError={logError}
              />
            )}
            {parser !== 'xml' && parser !== 'csv' && parser !== 'json' && (
              <PreviewForm
                id={id}
                parser={parser}
                complete={nextStep}
                settings={settings}
                onError={logError}
              />
            )}
          </ErrorBoundary>
        )}
        {step === 2 && (
          <ErrorBoundary>
            <TemplateForm
              id={id}
              template={template}
              parser={parser}
              settings={settings}
              complete={nextStep}
              enabled={enabled}
              onError={logError}
              pro={pro}
              templates={templates}
            />
          </ErrorBoundary>
        )}
        {step === 3 && (
          <ErrorBoundary>
            <PermissionForm
              id={id}
              template={template}
              complete={nextStep}
              permissions={permissions}
              onError={logError}
              settings={general_settings}
            />
          </ErrorBoundary>
        )}
        {step === 4 && (
          <ErrorBoundary>
            <ImporterForm
              id={id}
              complete={nextStep}
              template={template}
              settings={general_settings}
              onRun={runImport}
              onError={logError}
              pro={pro}
              templates={templates}
            />
          </ErrorBoundary>
        )}
        {step === 5 && <ImporterLogs id={id} />}
        {importerState && importerState.debug && (
          <React.Fragment>
            {step === 5 && <div className="iwp-debug-spacer">&nbsp;</div>}
            <button
              type="button"
              className="iwp-debug__toggle dashicons-before dashicons-editor-code"
              onClick={() => {
                setShowDebug((current) => !current);
              }}
            >
              {show_debug ? (
                <span>Hide Debug</span>
              ) : (
                <span>Show Debug</span>
              )}
            </button>
            {show_debug && (
              <ImporterDebug
                id={id}
                settings={importerState.debug.settings}
              />
            )}
          </React.Fragment>
        )}
      </React.Fragment>
    )
};

EditPage.propTypes = {
  id: PropTypes.number,
  location: PropTypes.object,
  history: PropTypes.object,
  pro: PropTypes.bool,
  templates: PropTypes.array,
};

const mapStateToProps = (state) => ({
  importer: state.importer.importer,
});

const mapDispatchToProps = { setImporter };

export default compose(
  withRouter,
  connect(mapStateToProps, mapDispatchToProps)
)(EditPage);
