import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../services/importer.service';
import Modal from '../modal/Modal';
import ProgressBar from '../progress-bar/ProgressBar';
import StatusMessage from '../status-message/StatusMessage';
import ImporterLogTable from '../importer-log-table/ImporterLogTable';

class ImportRunner extends Component {
  constructor(props) {
    super(props);

    this.state = {
      saving: true,
      modalTitle: 'Initialising Import.',
      modalContent: '',
      modalClosable: false,
      importer_log: [],
      modalLoading: true,
      progress: 0,
      complete: false,
      showModal: true,
      paused: -1,
      cancelled: -1,
      error: false,
    };

    this.import_ids = -1;
    this.delete_ids = -1;

    // this.statusSubject = null;

    this.run = this.run.bind(this);
    this.stop = this.stop.bind(this);
    this.showModal = this.showModal.bind(this);
    this.closeModal = this.closeModal.bind(this);
    this.togglePause = this.togglePause.bind(this);
    this.updateStatus = this.updateStatus.bind(this);
  }

  togglePause() {
    const { id, session } = this.props;
    let pause = 'yes';

    if (this.state.paused === 1) {
      pause = 'no';
    }

    if (this.state.paused === -1) {
      this.setState({ paused: 0 });
    } else if (this.state.paused === 1) {
      this.setState({ paused: 2 });
    }

    importer.pause(id, session, pause).then((response) => {
      if (response.s === 'timeout') {
        this.setState({ paused: -1 });
        this.run();
      }
    });
  }

  stop() {
    const { id, session } = this.props;
    this.runner.abort();
    this.setState({ cancelled: 0 });
    importer.stop(id, session).then(
      () => {
        this.setState({ showModal: false, cancelled: 1 });
        this.props.onComplete();
        document.title = this.document_title;
      },
      (error_msg) => {
        this.setState({
          importer_log: [['500', 'I', error_msg], ...this.state.importer_log],
          cancelled: 0,
          error: true,
        });
      }
    );
  }

  run() {
    const { id, session } = this.props;
    this.runner = importer.run(id, session);

    this.document_title = document.title;

    this.setState({ complete: false });

    this.runner.request.subscribe(
      (response) => {

        if (response.status !== 'S' || response.data.status === 'error') {
          // Error occured
          this.setState({
            modalTitle: 'A fatal error has occurred.',
            modalLoading: false,
            modalContent: <p>{response.data.message}</p>,
            error: true,
          });
          this.runner.abort();
          document.title = this.document_title;
          return;
        }

        if (response.data.status === 'complete') {
          this.runner.abort();
        }


        this.updateStatus(response.data);
      },
      (error) => {
        this.runner.abort();
        const { importer_log } = this.state;
        if (error.status > 0) {
          let error_msg = error.statusText;
          let error_code = error.status;
          if (
            error.responseJSON &&
            error.responseJSON.code &&
            error.responseJSON.message &&
            error.responseJSON.code === 'IWP_ERR'
          ) {
            error_msg = error.responseJSON.message;
            error_code = error.responseJSON.code;
          }
          // TODO: Update modal to say error has occurred.
          this.setState({
            modalTitle: 'A fatal error has occurred.',
            modalLoading: false,
            modalContent: <p>Error Code: {error_code}, Error Message: {error_msg}</p>,
            error: true,
            importer_log: [[error_code, 'I', error_msg], ...importer_log],
            cancelled: 0,
          });
        }
      }
    );
  }

  showModal() {
    this.setState({
      showModal: true,
    });
  }

  closeModal() {
    this.setState({
      showModal: false,
    });
  }

  componentDidMount() {
    this.run();
  }

  updateStatus(response) {
    let modalContent = (
      <p className="iwp-import__stats">
        <StatusMessage status={response} />
      </p>
    );

    if (response.status === 'error') {
      document.title = this.document_title;

      this.setState({
        modalTitle: 'A fatal error has occurred.',
        modalLoading: false,
        modalContent: <p>{response.message}</p>,
        error: true,
      });
      return;
    }

    if (response.status === 'complete') {
      document.title = this.document_title;

      this.setState({
        modalTitle: 'Complete.',
        modalContent: modalContent,
        progress: 100,
        saving: false,
        modalClosable: true,
        modalLoading: false,
        complete: true,
      });
      return;
    }

    if (response.section === 'import') {

      if (response.status === 'init') {

        // handle processing status?
        // counter is value 0-100
        const counter = +response.process;

        document.title = 'Processing File: ' + counter + '%';

        this.setState({
          modalTitle: 'Processing File.',
          progress: counter,
          modalContent
        });

      } else {

        const counter = response.progress.import.current_row;
        const total = response.progress.import.end - response.progress.import.start;

        document.title = 'Importing: (' + counter + '/' + total + ')';

        this.setState({
          modalTitle: 'Importing.',
          progress: ((counter / total) * 100).toFixed(),
          modalContent
        });
      }
    } else if (response.section === 'delete') {

      const counter = response.progress.delete.current_row;
      const total = response.progress.delete.end - response.progress.delete.start;

      document.title = 'Deleting: (' + counter + '/' + total + ')';

      this.setState({
        modalTitle: 'Deleting.',
        progress: ((counter / total) * 100).toFixed(),
        modalContent
      });
    }

    return;
  }

  componentDidUpdate(prevProps) {
    if (prevProps.status !== this.props.status) {
      // This should all be moved to render
      if (this.props.status != null) {
        const response = this.props.status;
        this.updateStatus(response);
      }
    }
  }

  render() {
    const {
      showModal,
      progress,
      modalTitle,
      modalContent,
      modalClosable,
      importer_log,
      modalLoading,
      paused,
      complete,
      cancelled,
      error,
    } = this.state;

    const { id, session } = this.props;

    return (
      <Modal
        title={modalTitle}
        onClose={() => {
          this.closeModal();
          this.props.onComplete();
        }}
        show={showModal}
        closable={modalClosable}
        loading={modalLoading && paused !== 1}
      >
        <ProgressBar progress={progress} text={progress + '%'} />

        {modalContent}

        {!error ? (
          <React.Fragment>
            {!complete ? (
              <React.Fragment>
                {/* <button
                  type="button"
                  className="button button-primary"
                  style={{ marginBottom: '20px' }}
                  onClick={this.togglePause}
                  disabled={cancelled > -1}
                >
                  {paused === -1 && 'Pause'}
                  {paused === 0 && 'Pausing'}
                  {paused === 1 && 'Resume'}
                  {paused === 2 && 'Resuming'}
                </button>{' '} */}
                <button
                  type="button"
                  className="button button-link-delete"
                  style={{ marginBottom: '20px' }}
                  onClick={this.stop}
                >
                  Cancel
                </button>
              </React.Fragment>
            ) : (
              <React.Fragment>
                <button
                  type="button"
                  onClick={() => {
                    this.closeModal();
                    this.props.onComplete();
                  }}
                  className="button button-secondary"
                  style={{ marginBottom: '20px' }}
                >
                  Close
                </button>
                <ImporterLogTable id={id} log={session} />
              </React.Fragment>
            )}
          </React.Fragment>
        ) : (
          <button
            type="button"
            onClick={() => {
              this.closeModal();
              this.props.onComplete();
            }}
            className="button button-link-delete"
            style={{ marginBottom: '20px' }}
          >
            Close
          </button>
        )}
      </Modal>
    );
  }
}

ImportRunner.propTypes = {
  id: PropTypes.number.isRequired,
  session: PropTypes.string.isRequired,
  onComplete: PropTypes.func,
};

ImportRunner.defaultProps = {
  onComplete: () => { },
};

export default ImportRunner;
