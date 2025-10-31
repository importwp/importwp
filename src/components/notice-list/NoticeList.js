import React, { Component } from 'react';
import PropTypes from 'prop-types';

class NoticeList extends Component {
  render() {
    const { notices } = this.props;
    return (
      <div>
        {notices.length > 0 && (
          <div className="iwp-notices">
            {notices.map((notice, i) => (
              <div
                key={`notice-${i}`}
                className={
                  'iwp-notice iwp-notice--' +
                  notice.type +
                  ' ' +
                  (notice.dismissed
                    ? 'iwp-notice--dismissed'
                    : 'iwp-notice--visible')
                }
              >
                <p>{notice.message}</p>
                {notice.dismissible && (
                  <button
                    onClick={() => this.props.onDismiss(i)}
                    title="Dismiss notice."
                  >
                    x
                  </button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    );
  }
}

NoticeList.propTypes = {
  notices: PropTypes.array,
  onDismiss: PropTypes.func,
};

NoticeList.defaultProps = {
  notices: [],
  onDismiss: () => {},
};

export default NoticeList;
