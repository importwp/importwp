import React from 'react';
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

const AJAX_BASE = window.iwp.admin_base;

class EditPage extends React.Component {
  constructor(props) {
    super(props);

    let form, step;
    if (props.id === null) {
      form = 'add';
      step = -1;
    } else {
      form = 'edit';
      step = 0;
    }

    this.resetImporter = {
      parser: null,
      file: null,
      files: {},
      permissions: {},
      settings: {},
    };

    this.state = {
      step: step,
      form: form,
      maxStep: step,
      init: false,
      importer: this.resetImporter,
      loading: true,
      datasource_type: null,
      datasource_settings: {},
      notices: [],
      run_importer: null,
      status: null,
      log: null,
      show_debug: false,
    };

    this.statusXHR = null;
    this.importerSubject = null;
    this.statusSubject = null;

    this.nextStep = this.nextStep.bind(this);
    this.gotoStep = this.gotoStep.bind(this);
    this.createImporter = this.createImporter.bind(this);
    this.getImporter = this.getImporter.bind(this);
    this.setMaxStep = this.setMaxStep.bind(this);
    this.runImport = this.runImport.bind(this);
    this.getStatus = this.getStatus.bind(this);
    this.logError = this.logError.bind(this);
  }

  componentDidUpdate() {
    console.log('componentDidUpdate', 'EditPage');
  }

  router() {
    const values = qs.parse(this.props.location.search);
    if (values.log !== 'undefined') {
      if (values.log !== '') {
        // show single log
      } else {
        // show log archive
      }
    }
  }

  logError(error) {
    let message = error;
    if (error.hasOwnProperty('statusText') && error.hasOwnProperty('status')) {
      message = `The following error has occured: ${error.statusText}, Code: ${error.status}`;
    }

    this.setState({
      notices: [
        ...this.state.notices,
        { message: message, type: 'error', dismissible: true },
      ],
    });
  }

  getActiveStep() {
    const { step } = qs.parse(this.props.location.search);

    if (step) {
      const activeStep = Math.min(this.state.maxStep, step);
      this.setState({ step: parseInt(activeStep) });
    } else {
      // if no step set it to max step
      this.setState({ step: this.state.maxStep > 4 ? 4 : this.state.maxStep });
    }
  }

  getImporter() {
    const { id } = this.props;
    this.setState({ loading: true });

    this.importerSubject = importer.getAndSubscribe(id).subscribe({
      next: (data) => {
        if (data !== null) {
          this.setState({
            init: true,
            importer: data,
            loading: false,
            datasource_type: data.datasource.type,
            datasource_settings: data.datasource.settings,
          });

          // TODO: dispatch importer to redux
          this.props.setImporter(data);

          this.setMaxStep(data);
          this.getActiveStep();

          if (this.statusXHR === null) {
            this.getStatus();
          }
        }
      },
      error: (error) => {
        this.logError(error);
        this.setState({ loading: false });
      },
    });
  }

  getStatus() {
    const { id } = this.props;
    this.statusXHR = importer.status([id]);
    this.statusSubject = this.statusXHR.request.subscribe(
      (response) => {
        this.setState({
          status: response.find((item) =>
            item?.version == 2 ? item.importer == id : item.id === id
          ),
        });
      },
      () => { }
    );
  }

  nextStep() {
    let { step, maxStep } = this.state;
    step++;

    if (step > maxStep) {
      this.setState({ maxStep: step });
    }

    if (step > 4) {
      step = 0;
    }

    this.setState({ step: step });
  }

  gotoStep(step) {
    this.setState({ step: step });
  }

  createImporter(id) {
    this.setState({ id: id });

    if (this.props.id === null) {
      this.props.history.push(AJAX_BASE + '&edit=' + id);
    }

    this.nextStep();
  }

  setMaxStep(importer) {
    let max = 0;

    if (importer.file && importer.file.id > 0) {
      max = 1;
    }

    if (
      importer.file &&
      importer.file.settings &&
      importer.file.settings.setup === true
    ) {
      max = 2;
    }

    if (importer.map && Object.keys(importer.map).length > 0) {
      max = 3;
    }

    if (importer.permissions) {
      if (
        (importer.permissions.create &&
          importer.permissions.create.enabled === true) ||
        (importer.permissions.update &&
          importer.permissions.update.enabled === true) ||
        (importer.permissions.remove &&
          importer.permissions.remove.enabled === true)
      ) {
        max = 5;
      }
    }

    this.setState({ maxStep: max });
  }

  runImport(session) {
    if (this.statusXHR !== null) {
      this.statusXHR.abort();
      this.statusXHR = null;
    }

    this.setState({ run_importer: session });
  }

  componentDidMount() {
    if (this.props.id > 0) {
      this.setState({ maxStep: 0 });
      this.getImporter();
    } else {
      this.setState({
        form: 'add',
        step: -1,
        maxStep: -1,
        loading: false,
        init: true,
      });
    }
  }

  componentDidUpdate(prevProps, prevState) {
    const id = parseInt(this.props.id) || 0;
    const prevId = parseInt(prevProps.id) || 0;
    if (id !== prevId) {
      if (id > 0) {
        this.getImporter();
      } else {
        this.setState({
          form: 'add',
          step: -1,
          maxStep: -1,
          notices: [],
          importer: this.resetImporter,
        });
      }
    }

    const step = parseInt(this.state.step) || 0;
    const prevStep = parseInt(prevState.step) || 0;
    if (step !== prevStep) {
      if (this.props.id !== null) {
        // keep log if part of url
        const { log } = qs.parse(this.props.location.search);
        let url = AJAX_BASE + '&edit=' + id + '&step=' + step;
        if (log && step === 5) {
          url += '&log=' + log;
        }

        this.props.history.push(url);
      }
    }
  }

  componentWillUnmount() {
    // abort ajax requests
    if (this.statusXHR) {
      this.statusXHR.abort();
    }
    importer.abort();

    // unsubscribe from subjects
    if (this.importerSubject !== null) {
      this.importerSubject.unsubscribe();
    }
    if (this.statusSubject !== null) {
      this.statusSubject.unsubscribe();
    }
  }

  render() {
    const {
      step,
      maxStep,
      form,
      datasource_type,
      datasource_settings,
      notices,
      init,
      run_importer,
      status,
    } = this.state;
    const { id } = this.props;
    const { template, parser, file, files, enabled, permissions } =
      this.state.importer;
    const settings = file ? file.settings : null;

    if (init === false) {
      return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
    }

    const general_settings = this.state.importer.settings;

    return (
      <React.Fragment>
        <GlobalNotice />

        {run_importer !== null && (
          <ImportRunner
            id={id}
            session={run_importer}
            status={status}
            onComplete={() => {
              this.setState({ run_importer: null, status: null });

              if (this.statusXHR === null) {
                this.getStatus();
              }
            }}
          />
        )}

        <EditSteps
          id={id}
          step={step}
          form={form}
          gotoStep={this.gotoStep}
          maxStep={maxStep}
          importer={this.state.importer}
          onError={this.logError}
        />

        {id > 0 &&
          status &&
          status?.version === 2 &&
          (status.status === 'running' || status.status === 'processing' || status?.cron) && (
            <NoticeList
              notices={[
                {
                  message: (
                    <React.Fragment>
                      <StatusMessage status={status} />
                      {(status?.status === 'running' || status?.status === 'processing') && (
                        <div className="iwp-notice__actions">
                          {!status.hasOwnProperty('cron') && (
                            <button
                              type="button"
                              className="button-link-continue"
                              onClick={() => {
                                // TODO: how do we resume
                                if (status?.id) {
                                  this.runImport(status.id);
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
            let temp = this.state.notices;
            temp[i].dismissed = true;
            this.setState({ notices: temp });
          }}
        />

        {step === -1 && (
          <ErrorBoundary>
            <SetupForm
              id={id}
              complete={this.createImporter}
              template={template}
              onError={this.logError}
              templates={this.props.templates}
            />
          </ErrorBoundary>
        )}
        {step === 0 && (
          <ErrorBoundary>
            <DatasourceForm
              id={id}
              complete={this.nextStep}
              parser={parser}
              file={file ? file.id : null}
              files={files}
              datasource={datasource_type}
              settings={datasource_settings}
              onError={this.logError}
            />
          </ErrorBoundary>
        )}
        {step === 1 && (
          <ErrorBoundary>
            {parser === 'xml' && (
              <PreviewXmlForm
                id={id}
                complete={this.nextStep}
                settings={settings}
                onError={this.logError}
              />
            )}
            {parser === 'csv' && (
              <PreviewCsvForm
                id={id}
                complete={this.nextStep}
                settings={settings}
                onError={this.logError}
              />
            )}
            {parser !== 'xml' && parser !== 'csv' && (
              <PreviewForm
                id={id}
                parser={parser}
                complete={this.nextStep}
                settings={settings}
                onError={this.logError}
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
              complete={this.nextStep}
              enabled={enabled}
              onError={this.logError}
              pro={this.props.pro}
              templates={this.props.templates}
            />
          </ErrorBoundary>
        )}
        {step === 3 && (
          <ErrorBoundary>
            <PermissionForm
              id={id}
              template={template}
              complete={this.nextStep}
              permissions={permissions}
              onError={this.logError}
              settings={general_settings}
            />
          </ErrorBoundary>
        )}
        {step === 4 && (
          <ErrorBoundary>
            <ImporterForm
              id={id}
              complete={this.nextStep}
              template={template}
              settings={general_settings}
              onRun={this.runImport}
              onError={this.logError}
              pro={this.props.pro}
              templates={this.props.templates}
            />
          </ErrorBoundary>
        )}
        {step === 5 && <ImporterLogs id={id} />}
        {this.state.importer && this.state.importer.debug && (
          <React.Fragment>
            {step === 5 && <div className="iwp-debug-spacer">&nbsp;</div>}
            <button
              type="button"
              className="iwp-debug__toggle dashicons-before dashicons-editor-code"
              onClick={() => {
                this.setState({ show_debug: !this.state.show_debug });
              }}
            >
              {this.state.show_debug ? (
                <span>Hide Debug</span>
              ) : (
                <span>Show Debug</span>
              )}
            </button>
            {this.state.show_debug && (
              <ImporterDebug
                id={this.props.id}
                settings={this.state.importer.debug.settings}
              />
            )}
          </React.Fragment>
        )}
      </React.Fragment>
    );
  }
}

EditPage.propTypes = {
  id: PropTypes.number,
  location: PropTypes.object,
  history: PropTypes.object,
  pro: PropTypes.bool,
  templates: PropTypes.array,
};

EditPage.defaultProps = {
  id: null,
  pro: false,
  templates: [],
};

const mapStateToProps = (state) => ({
  importer: state.importer.importer,
});

const mapDispatchToProps = { setImporter };

export default compose(
  withRouter,
  connect(mapStateToProps, mapDispatchToProps)
)(EditPage);
