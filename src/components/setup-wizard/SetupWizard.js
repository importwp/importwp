import React, { useCallback, useEffect, useState } from 'react';
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

function SetupWizard({ onComplete = () => { } }) {
  const [rest_enabled, setRestEnabled] = useState(-1);
  const [system, setSystem] = useState({});
  const [migrated, setMigrated] = useState(-2);
  const [complete, setComplete] = useState(false);
  const [show, setShow] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const requirementsMet = useCallback((currentSystem) => {
    if (
      !currentSystem.rest_enabled ||
      currentSystem.rest_enabled.status !== 'yes'
    ) {
      return false;
    }

    if (
      !currentSystem.php_version ||
      currentSystem.php_version.status !== 'yes'
    ) {
      return false;
    }

    if (
      !currentSystem.tmp_writable ||
      currentSystem.tmp_writable.status !== 'yes'
    ) {
      return false;
    }

    return true;
  }, []);

  const setupComplete = useCallback(() => {
    setComplete(true);
    setLoading(false);
  }, []);

  const checkMigrationStatus = useCallback(() => {
    importer.migrate().then(
      () => {
        setMigrated(1);
        setupComplete();
      },
      () => {
        setMigrated(0);
      }
    );
  }, [setupComplete]);

  const checkRestStatus = useCallback(() => {
    importer.check().then(
      (data) => {
        setRestEnabled(1);
        setSystem(data);
        if (requirementsMet(data)) {
          checkMigrationStatus();
        } else {
          setLoading(false);
          setError('System Requirements have not been met.');
        }
      },
      () => {
        setRestEnabled(0);
        setLoading(false);
        setError(
          'Plugin is unable to communicate with your websites WordPress REST API, please make sure this has not been disabled.'
        );
      }
    );
  }, [checkMigrationStatus, requirementsMet]);

  useEffect(() => {
    checkRestStatus();
  }, [checkRestStatus]);

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
      {
        label: 'PHP Module: Zip Archive',
        key: 'zip_archive'
      }
    ];
    return (
      <React.Fragment>
      <Modal
          title="Import WP: Setup Wizard"
          onClose={onComplete}
          loading={loading}
          closable={false}
        show={show}
      >
          <div className="wizard-section">
            <h4>1. System Check.</h4>
          {rest_enabled === -1 ? (
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
                          {system[data.key] &&
                            system[data.key].status === 'yes' ? (
                              <span style={{ color: 'green' }}>Yes</span>
                            ) : (
                              <span style={{ color: 'red' }}>No</span>
                            )}
                          </td>
                          <td style={colStyles(2)}>
                          {system[data.key] &&
                            system[data.key].message}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </React.Fragment>
            )}
          </div>

        {rest_enabled > -1 && migrated >= -1 && (
            <div className="wizard-section">
              <h4>2. Data migration.</h4>
            {migrated === -1 && <p>Migrating data.</p>}
            {migrated === 0 && <p>Unable to migrate data.</p>}
            {migrated === 1 && <p>Data migration complete.</p>}
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

        {complete && requirementsMet(system) && (
            <button
              type="button"
              onClick={() => {
              setShow(false);
              onComplete();
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

SetupWizard.propTypes = {
  onComplete: PropTypes.func,
};

export default SetupWizard;
