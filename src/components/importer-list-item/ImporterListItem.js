import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';

import { importer as importerService } from '../../services/importer.service';
import './ImporterListItem.scss';
import StatusMessage from '../status-message/StatusMessage';
import { getSectionProgressBarWidth } from './progress';

const AJAX_BASE = window.iwp.admin_base;

const ImporterListItem = ({
  importer,
  status = { s: 'loading' },
  onDelete = () => {},
}) => {
  const { id, parser, name } = importer;
  let { template } = importer;

  // TODO: remove duplication of this code with EditSteps
  if (template === 'custom-post-type') {
    template = 'Custom Post Type: ' + importer.settings.post_type;
  } else if (template === 'term') {
    template = 'Taxonomy: ' + importer.settings.taxonomy;
  }

  // rename status variables, shortened to save bytes
  const version = status?.id ? 2 : 1;
  const statusValue = version === 2 ? status.status : status.s;
  const total = status.t > 0 ? status.t : 0;
  const counter = status.c > 0 ? status.c : 0;
  const delete_counter = status.r > 0 ? status.r : 0;
  const delete_total = status.a > 0 ? status.a : 0;
  const progressBarWidth = version === 2 ? getSectionProgressBarWidth(status) : null;

  let msg = 'Loading.';

  if (version === 2) {

    if (statusValue === 'error') {
      msg =
        'Import Error' +
        (status.message !== null ? ': ' + status.message : '.');
    } else if (statusValue !== 'loading') {
      msg = <StatusMessage status={{ msg: status.message }} />;
    }

  } else {

    if (statusValue === 'error') {
      msg =
        'Import Error' +
        (status.m !== null ? ': ' + status.m : '.');
    } else if (statusValue !== 'loading') {
      msg = <StatusMessage status={status} />;
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
                  importerService.remove(id).then(() => onDelete(id));
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
            (statusValue == 'running' || statusValue == 'processing') && progressBarWidth !== null && <div
              className="iwp-item__progress-bar"
              style={{ width: progressBarWidth + '%' }}
            ></div>
          }
        </> : <>
          {(statusValue === 'running' || statusValue == 'processing' || statusValue === 'timeout') &&
            delete_counter === 0 && (
              <div
                className="iwp-item__progress-bar"
                style={{ width: 100 - (counter / total) * 100 + '%' }}
              ></div>
            )}
          {(statusValue === 'running' || statusValue == 'processing' || statusValue === 'timeout') &&
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
};

ImporterListItem.propTypes = {
  importer: PropTypes.object.isRequired,
  status: PropTypes.object,
  onDelete: PropTypes.func,
};

export default ImporterListItem;
