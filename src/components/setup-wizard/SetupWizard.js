import React, { Component } from 'react';
import PropTypes from 'prop-types';

import Modal from '../modal/Modal';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';

import './SetupWizard.scss';

const colStyles = (index) => {
  if (index === 1) {
    return {
      width: '10%',
    };
  }
  return {
    width: '45%',
  };
};

class SetupWizard extends Component {
  constructor(props) {
    super(props);

    this.state = {
      rest_enabled: -1,
      system: {},
      migrated: -2,
      complete: false,
      show: true,
      loading: true,
      error: '',
    };

    this.checkRestStatus = this.checkRestStatus.bind(this);
    this.checkMigrationStatus = this.checkMigrationStatus.bind(this);
    this.setupComplete = this.setupComplete.bind(this);
    this.requirementsMet = this.requirementsMet.bind(this);
  }

  checkRestStatus() {
    importer.check().then(
      (data) => {
        this.setState({ rest_enabled: 1, system: data });
        if (this.requirementsMet()) {
          this.checkMigrationStatus();
        } else {
          this.setState({
            loading: false,
            error: 'System Requirements have not been met.',
          });
        }
      },
      () => {
        this.setState({
          rest_enabled: 0,
          loading: false,
          error:
            'Plugin is unable to communicate with your websites WordPress REST API, please make sure this has not been disabled.',
        });
      }
    );
  }

  checkMigrationStatus() {
    importer.migrate().then(
      () => {
        this.setState({ migrated: 1 });
        this.setupComplete();
      },
      () => {
        this.setState({ migrated: 0 });
      }
    );
  }

  setupComplete() {
    this.setState({ complete: true, loading: false });
  }

  requirementsMet() {
    if (
      !this.state.system.rest_enabled ||
      this.state.system.rest_enabled.status !== 'yes'
    ) {
      return false;
    }

    if (
      !this.state.system.php_version ||
      this.state.system.php_version.status !== 'yes'
    ) {
      return false;
    }

    if (
      !this.state.system.tmp_writable ||
      this.state.system.tmp_writable.status !== 'yes'
    ) {
      return false;
    }

    return true;
  }

  componentDidMount() {
    this.checkRestStatus();
  }

  render() {
    const { complete, loading, error } = this.state;
    const system_checks = [
      {
        label: 'Rest API Enabled',
        key: 'rest_enabled',
      },
      {
        label: 'Temp directory writable',
        key: 'tmp_writable',
      },
      {
        label: 'PHP Version >= 5.5',
        key: 'php_version',
      },
      {
        label: 'PHP Module: SimpleXML',
        key: 'ext_simplexml',
      },
      {
        label: 'PHP Module: mbstring',
        key: 'ext_mbstring',
      },
      {
        label: 'PHP Module: XML Reader',
        key: 'ext_xmlreader',
      },
    ];
    return (
      <React.Fragment>
        <Modal
          title="Import WP: Setup Wizard"
          onClose={this.props.onComplete}
          loading={loading}
          closable={false}
          show={this.state.show}
        >
          <div className="wizard-section">
            <h4>1. System Check.</h4>
            {this.state.rest_enabled === -1 ? (
              <p>Checking...</p>
            ) : (
              <React.Fragment>
                <div className="iwp-table__wrapper">
                  <table className="iwp-table iwp-table--fixed iwp-table--logs">
                    <thead>
                      <tr>
                        <th style={colStyles(0)}>Module</th>
                        <th style={colStyles(1)}>Status</th>
                        <th style={colStyles(2)}>Message</th>
                      </tr>
                    </thead>
                    <tbody>
                      {system_checks.map((data) => (
                        <tr key={data.key}>
                          <td style={colStyles(0)}>{data.label}</td>
                          <td style={colStyles(1)}>
                            {this.state.system[data.key] &&
                            this.state.system[data.key].status === 'yes' ? (
                              <span style={{ color: 'green' }}>Yes</span>
                            ) : (
                              <span style={{ color: 'red' }}>No</span>
                            )}
                          </td>
                          <td style={colStyles(2)}>
                            {this.state.system[data.key] &&
                              this.state.system[data.key].message}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </React.Fragment>
            )}
          </div>

          {this.state.rest_enabled > -1 && this.state.migrated >= -1 && (
            <div className="wizard-section">
              <h4>2. Data migration.</h4>
              {this.state.migrated === -1 && <p>Migrating data.</p>}
              {this.state.migrated === 0 && <p>Unable to migrate data.</p>}
              {this.state.migrated === 1 && <p>Data migration complete.</p>}
            </div>
          )}

          {error && (
            <NoticeList
              notices={[
                {
                  message: <React.Fragment>{error}</React.Fragment>,
                  type: 'error',
                },
              ]}
            />
          )}

          {complete && this.requirementsMet() && (
            <button
              type="button"
              onClick={() => {
                this.setState({ show: false });
                this.props.onComplete();
              }}
              className="button button-secondary"
            >
              Close &amp; Continue
            </button>
          )}
        </Modal>
      </React.Fragment>
    );
  }
}

SetupWizard.propTypes = {
  onComplete: PropTypes.func,
};

SetupWizard.defaultProps = {
  onComplete: () => {},
};

export default SetupWizard;
