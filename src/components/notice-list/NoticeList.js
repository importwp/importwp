import PropTypes from 'prop-types';

const NoticeList = ({ notices = [], onDismiss = () => {} }) => {
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
                  onClick={() => onDismiss(i)}
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
};

NoticeList.propTypes = {
  notices: PropTypes.array,
  onDismiss: PropTypes.func,
};

export default NoticeList;
