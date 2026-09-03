import PropTypes from 'prop-types';

import './ProgressBar.scss';

const ProgressBar = ({ progress = -1, text }) => {
  const progressValue = Number(progress);

  return (
    <div className="iwp-progress__wrapper">
      <div className="iwp-progress__inner">
        {text && <div className="iwp-progress__text">{text}</div>}
        <div
          className="iwp-progress__bar"
          style={{ width: progressValue + '%' }}
        ></div>
      </div>
    </div>
  );
};

ProgressBar.propTypes = {
  progress: PropTypes.number,
  text: PropTypes.string
};

export default ProgressBar;
