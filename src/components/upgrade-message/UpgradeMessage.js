import React from 'react';
import PropTypes from 'prop-types';

class UpgradeMessage extends React.Component {
  render() {
    const { message } = this.props;
    return (
      <div className="iwp-notice iwp-notice--premium">
        <p>{message}</p>
      </div>
    );
  }
}

UpgradeMessage.propTypes = {
  message: PropTypes.string.isRequired
};

export default UpgradeMessage;
