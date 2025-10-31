import React, { Component } from 'react';
import PropTypes from 'prop-types';

import './ProgressBar.scss';

class ProgressBar extends Component {
  render() {
    const { progress, text } = this.props;
    return (
      <div className="iwp-progress__wrapper">
        <div className="iwp-progress__inner">
          {text && <div className="iwp-progress__text">{text}</div>}
          <div
            className="iwp-progress__bar"
            style={{ width: progress + '%' }}
          ></div>
        </div>
      </div>
    );
  }
}

ProgressBar.propTypes = {
  progress: PropTypes.number,
  text: PropTypes.string
};

ProgressBar.defaultProps = {
  progress: -1
};

export default ProgressBar;
