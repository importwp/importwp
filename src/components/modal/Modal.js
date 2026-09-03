import PropTypes from 'prop-types';

import './Modal.scss';

const Modal = ({
  onClose,
  show,
  children,
  closable = true,
  title = 'Importer',
  loading = false,
}) => {
  const handleClose = (e) => {
    return onClose ? onClose(e) : () => {};
  };

  if (!show) {
    return null;
  }
  return (
    <div className="iwp-modal">
      <div
        className="iwp-modal__backdrop"
        onClick={closable ? handleClose : null}
      />
      <div className="iwp-modal__wrapper">
        <div className="iwp-modal__inside">
          {closable && (
            <span
              className="iwp-modal__close"
              onClick={closable ? handleClose : null}
            >
              x
            </span>
          )}
          {loading && <div className="spinner is-active"></div>}
          <h2 className="iwp-modal__title">{title}</h2>
          <div className="iwp-modal__content">{children}</div>
        </div>
      </div>
    </div>
  );
};

Modal.propTypes = {
  onClose: PropTypes.func.isRequired,
  show: PropTypes.bool.isRequired,
  children: PropTypes.any,
  closable: PropTypes.bool,
  title: PropTypes.string,
  loading: PropTypes.bool
};

export default Modal;
