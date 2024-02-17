import React from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';

import Icon from './icon.svg';

import './PageHeader.scss';

const ADMIN_BASE = window.iwp.admin_base;
const PLUGIN_VERSION = window.iwp.version;

const PageHeader = ({ active, pro }) => {
  return (
    <React.Fragment>
      <div className="iwp-header">
        <div className='iwp-header__inside'>
          <p className="iwp-header__heading">
            <span
              className="iwp-brand"
              style={{
                backgroundImage: `url(${Icon})`,
              }}
            >
              Import WP {pro === true ? 'PRO ' : ''}
            </span>
            {PLUGIN_VERSION !== '__STABLE_TAG__' && (
              <small>{PLUGIN_VERSION}</small>
            )}
          </p>

          <div className="iwp-header__tabs">
            <Link
              to={ADMIN_BASE}
              className={
                'iwp-header__tab' +
                (active === 'archive' || active === 'edit' || active === 'new'
                  ? ' iwp-header__tab--active'
                  : '')
              }
            >
              Importers
            </Link>
            <Link
              to={ADMIN_BASE + '&tab=exporters'}
              className={
                'iwp-header__tab' +
                (active === 'exporters' ||
                  active === 'edit-exporter' ||
                  active === 'new-exporter'
                  ? ' iwp-header__tab--active'
                  : '')
              }
            >
              Exporters
            </Link>
            <Link
              to={ADMIN_BASE + '&tab=settings'}
              className={
                'iwp-header__tab' +
                (active === 'settings' ? ' iwp-header__tab--active' : '')
              }
            >
              Settings / Tools
            </Link>
            {pro === true ? (
              <a
                href="https://www.importwp.com/documentation/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=navigation"
                className="iwp-header__tab"
                target="_blank"
                rel="noopener noreferrer"
              >
                Docs
              </a>
            ) : (
              <a
                href="https://www.importwp.com/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=Import%2BWP%2BFree&utm_medium=navigation"
                className="iwp-header__tab iwp-header__tab--icon"
                target="_blank"
                rel="noopener noreferrer"
              >
                <span className="dashicons dashicons-star-filled"></span> PRO
              </a>
            )}
          </div>
        </div>
      </div>
      <div className="iwp-wp-notices">
        <div className="wp-header-end"></div>
      </div>
    </React.Fragment>
  );
};

PageHeader.propTypes = {
  active: PropTypes.string,
  pro: PropTypes.bool,
};

PageHeader.defaultProps = {
  active: 'archive',
  pro: false,
};

export default PageHeader;
