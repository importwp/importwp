import React from 'react';
import './PremiumPage.scss';
import PlaceHolder from './iwp-premium-placeholder.png';

const PLUGIN_URL = window.iwp.plugin_url;

class PremiumPage extends React.Component {
  render() {
    return (
      <div>
        <div className="iwp-list iwp-list--premium">
          <div className="iwp-list__item">
            <div className="iwp-item__left">
              <img
                src={PLUGIN_URL.replace(/\/$/, '') + PlaceHolder}
                className="iwp-img__responsive"
              />
            </div>
            <div className="iwp-item__right">
              <div className="iwp-item__content">
                <h2>Import into custom Post-Types and Taxonomies</h2>
                <p>
                  Import WP PRO allows you to import into any custom post type
                  or taxonomy that has been registered by any WordPress theme or
                  plugin.
                </p>
              </div>
            </div>
          </div>

          <div className="iwp-list__item iwp-list__item--flip">
            <div className="iwp-item__left">
              <img
                src={PLUGIN_URL.replace(/\/$/, '') + PlaceHolder}
                className="iwp-img__responsive"
              />
            </div>
            <div className="iwp-item__right">
              <div className="iwp-item__content">
                <h2>Import custom fields.</h2>
                <p>
                  Unlock the ability to import custom fields into post types,
                  taxonomies, posts, pages and users. We offer some built in
                  manipulation of data, such as image downloading, data
                  serialization, or modify it yourself using WordPress filters.
                </p>
              </div>
            </div>
          </div>
          <div className="iwp-list__item">
            <div className="iwp-item__left">
              <img
                src={PLUGIN_URL.replace(/\/$/, '') + PlaceHolder}
                className="iwp-img__responsive"
              />
            </div>
            <div className="iwp-item__right">
              <div className="iwp-item__content">
                <h2>Schedule imports.</h2>
                <p>
                  Dont want to run your import right away, you can schedule the
                  import to run at a specific time and run the import on a set
                  interval, allowing you to easily keep your website content
                  upto date.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="iwp-buttons iwp-buttons--center">
          <a
            href="https://www.importwp.com/pricing/?utm_source=plugin&utm_medium=upgrade"
            className="iwp-button iwp-button--buy"
          >
            Buy Now
          </a>
        </div>
      </div>
    );
  }
}

export default PremiumPage;
