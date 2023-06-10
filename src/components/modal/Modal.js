import React from 'react';
import PropTypes from 'prop-types';

import './Modal.scss';

export default class Modal extends React.Component {
  constructor(props) {
    super(props);

    this.onClose = this.onClose.bind(this);
  }

  onClose(e) {
    return this.props.onClose ? this.props.onClose(e) : () => {};
  }
  render() {
    const { show, closable, title, loading } = this.props;

    if (!show) {
      return null;
    }
    return (
      <div className="iwp-modal">
        <div
          className="iwp-modal__backdrop"
          onClick={closable ? this.onClose : null}
        />
        <div className="iwp-modal__wrapper">
          <div className="iwp-modal__inside">
            {closable && (
              <span
                className="iwp-modal__close"
                onClick={closable ? this.onClose : null}
              >
                x
              </span>
            )}
            {loading && <div className="spinner is-active"></div>}
            <h2 className="iwp-modal__title">{title}</h2>
            <div className="iwp-modal__content">{this.props.children}</div>
          </div>
        </div>
      </div>
    );
  }
}

Modal.propTypes = {
  onClose: PropTypes.func.isRequired,
  show: PropTypes.bool.isRequired,
  children: PropTypes.any,
  closable: PropTypes.bool,
  title: PropTypes.string,
  loading: PropTypes.bool
};

Modal.defaultProps = {
  closable: true,
  title: 'Importer',
  loading: false
};
