import PropTypes from 'prop-types';

const UpgradeMessage = ({ message }) => {
  return (
    <div className="iwp-notice iwp-notice--premium">
      <p>{message}</p>
    </div>
  );
};

UpgradeMessage.propTypes = {
  message: PropTypes.string.isRequired
};

export default UpgradeMessage;
