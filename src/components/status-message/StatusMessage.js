import React, { Component } from 'react';
import PropTypes from 'prop-types';

class StatusMessage extends Component {
  render() {
    const { status } = this.props;
    return <React.Fragment>{status?.version == 2 ? status.message : status.msg}</React.Fragment>;
  }
}

StatusMessage.propTypes = {
  status: PropTypes.object.isRequired,
  showStatus: PropTypes.bool,
};
StatusMessage.defaultProps = {
  showStatus: false,
};

export default StatusMessage;
