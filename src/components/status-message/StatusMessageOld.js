import React, { Component } from 'react';
import PropTypes from 'prop-types';

class StatusMessageOld extends Component {
  render() {
    const { status } = this.props;

    if (status?.id) {

      if (status.status === 'error') {
        return <>{status.message}</>;
      }

      return <>
        {status.status && <span>
          <strong>Status</strong>: {status.status}
        </span>}
        {status.stats.inserts > 0 && <span>
          , <strong>Inserts</strong>: {status.stats.inserts}
        </span>}
        {status.stats.updates > 0 && <span>
          , <strong>Updates</strong>: {status.stats.updates}
        </span>}
        {status.stats.deletes > 0 && <span>
          , <strong>Deletes</strong>: {status.stats.deletes}
        </span>}
        {status.stats.errors > 0 && <span>
          , <strong>Errors</strong>: {status.stats.errors}
        </span>}
        {status.duration > 0 && <span>
          , <strong>Time</strong>: {Math.ceil(status.duration)}s
        </span>}
      </>
    }


    let time = status.z;
    let hours = Math.floor(time / 3600);
    time = time - hours * 3600;
    let minutes = Math.floor(time / 60);
    let seconds = time - minutes * 60;

    let status_txt = status.s;

    return (
      <React.Fragment>
        {status_txt && (
          <span>
            <strong>Status</strong>: {status_txt + ', '}
          </span>
        )}
        <span>
          <strong>Records</strong>: {status.c} / {status.t}
        </span>
        {status.i > 0 && (
          <span>
            , <strong>Inserts</strong>: {status.i}
          </span>
        )}
        {status.u > 0 && (
          <span>
            , <strong>Updates</strong>: {status.u}
          </span>
        )}
        {status.r > 0 && (
          <span>
            , <strong>Deletes</strong>: {status.r}
          </span>
        )}
        {status.e > 0 && (
          <span>
            , <strong>Errors</strong>: {status.e}
          </span>
        )}
        {status.z >= 0 && (
          <span>
            , <strong>Time:</strong>
            {hours > 0 && <span> {hours}h </span>}
            {minutes > 0 && <span> {minutes}m </span>}
            <span> {seconds}s</span>
          </span>
        )}
        {status.x && (
          <span>
            , <strong>Peak Memory:</strong>
            <span> {status.x}</span>
          </span>
        )}
      </React.Fragment>
    );
  }
}

StatusMessageOld.propTypes = {
  status: PropTypes.object.isRequired,
  showStatus: PropTypes.bool,
};
StatusMessageOld.defaultProps = {
  showStatus: false,
};

export default StatusMessageOld;
