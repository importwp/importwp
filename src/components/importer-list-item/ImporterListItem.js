import React from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';

import { importer } from '../../services/importer.service';
import './ImporterListItem.scss';
import StatusMessage from '../status-message/StatusMessage';

const AJAX_BASE = window.iwp.admin_base;

class ImporterListItem extends React.Component {
  render() {
    const { id, parser, name } = this.props.importer;
    let { template } = this.props.importer;

    // TODO: remove duplication of this code with EditSteps
    if (template === 'custom-post-type') {
      template = 'Custom Post Type: ' + this.props.importer.settings.post_type;
    } else if (template === 'term') {
      template = 'Taxonomy: ' + this.props.importer.settings.taxonomy;
    }

    // rename status variables, shortened to save bytes
    const version = this.props.status?.id ? 2 : 1;
    const status = version === 2 ? this.props.status.status : this.props.status.s;
    const total = this.props.status.t > 0 ? this.props.status.t : 0;
    const counter = this.props.status.c > 0 ? this.props.status.c : 0;
    const delete_counter = this.props.status.r > 0 ? this.props.status.r : 0;
    const delete_total = this.props.status.a > 0 ? this.props.status.a : 0;

    let msg = 'Loading.';

    if (version === 2) {

      if (status === 'error') {
        msg =
          'Import Error' +
          (this.props.status.message !== null ? ': ' + this.props.status.message : '.');
      } else if (status !== 'loading') {
        msg = <StatusMessage status={{ msg: this.props.status.message }} />;
      }

    } else {

      if (status === 'error') {
        msg =
          'Import Error' +
          (this.props.status.m !== null ? ': ' + this.props.status.m : '.');
      } else if (status !== 'loading') {
        msg = <StatusMessage status={this.props.status} />;
      }

    }

    return (
      <div className="iwp-importer-list__item">
        <div className="iwp-item">
          <div className="iwp-item__left">
            <h2 className="iwp-heading">{name}</h2>
            <p>
              Importing <strong>{template}</strong> from{' '}
              <strong>{parser}</strong>.
            </p>
          </div>
          <div className="iwp-item__right">
            <div className="iwp-buttons">
              <Link
                to={AJAX_BASE + '&edit=' + id}
                className="button button-primary button-small"
              >
                View
              </Link>
              <Link
                to={AJAX_BASE + '&edit=' + id + '&step=5'}
                className="button button-secondary button-small"
              >
                History
              </Link>
              <button
                type="button"
                onClick={() => {
                  var result = confirm(
                    'Are you sure you want to delete Importer #' +
                    id +
                    ' ' +
                    name
                  );
                  if (result) {
                    // TODO: Move this into the archive list component.
                    importer.remove(id).then(() => this.props.onDelete(id));
                  }
                }}
                className="button button-link-delete button-small"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
        <div className="iwp-item__progress">
          <p>{msg}</p>
          {version == 2 ? <>
            {
              status == 'running' && <div
                className="iwp-item__progress-bar"
                style={{ width: 100 - (this.props.status.progress[this.props.status.section].current_row / (this.props.status.progress[this.props.status.section].end - this.props.status.progress[this.props.status.section].start)) * 100 + '%' }}
              ></div>
            }
          </> : <>
            {(status === 'running' || status === 'timeout') &&
              delete_counter === 0 && (
                <div
                  className="iwp-item__progress-bar"
                  style={{ width: 100 - (counter / total) * 100 + '%' }}
                ></div>
              )}
            {(status === 'running' || status === 'timeout') &&
              delete_counter > 0 && (
                <div
                  className="iwp-item__progress-bar"
                  style={{
                    width: 100 - (delete_counter / delete_total) * 100 + '%',
                  }}
                ></div>
              )}
          </>}

        </div>
      </div>
    );
  }
}

ImporterListItem.propTypes = {
  importer: PropTypes.object.isRequired,
  status: PropTypes.object,
  onDelete: PropTypes.func,
};

ImporterListItem.defaultProps = {
  status: {
    s: 'loading',
  },
  onDelete: () => { },
};

export default ImporterListItem;
