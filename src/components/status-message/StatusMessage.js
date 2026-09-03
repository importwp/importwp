import PropTypes from 'prop-types';

const StatusMessage = ({ status, showStatus = false }) => {
  return <>{status?.version == 2 ? status.message : status.msg}</>;
};

StatusMessage.propTypes = {
  status: PropTypes.object.isRequired,
  showStatus: PropTypes.bool,
};

export default StatusMessage;
