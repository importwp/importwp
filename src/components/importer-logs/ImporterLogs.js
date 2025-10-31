import React, { Component } from 'react';
import { withRouter } from 'react-router';
import PropTypes from 'prop-types';

import qs from 'qs';
import ImporterLogArchive from '../importer-log-archive/ImporterLogArchive';
import ImporterLogSingle from '../importer-log-single/ImporterLogSingle';
import NoticeList from '../notice-list/NoticeList';

const AJAX_BASE = window.iwp.admin_base;

class ImporterLogs extends Component {
  constructor(props) {
    super(props);

    this.state = {
      log: null,
      isLoading: true,
    };

    this.setLog = this.setLog.bind(this);
    this.getActiveLog = this.getActiveLog.bind(this);
  }

  getActiveLog() {
    const { log } = qs.parse(this.props.location.search);
    this.setState({ log: log ? log : null });
  }

  setLog(log) {
    const { id } = this.props;

    if (log) {
      this.props.history.push(
        AJAX_BASE + '&edit=' + id + '&step=' + 5 + '&log=' + log
      );
    } else {
      this.props.history.push(AJAX_BASE + '&edit=' + id + '&step=' + 5);
    }

    this.setState({ log: log ? log : null });
  }

  componentDidMount() {
    this.getActiveLog();
    this.setState({ isLoading: false });
  }

  render() {
    const { id } = this.props;
    const { log, isLoading } = this.state;

    if (isLoading) {
      return (
        <NoticeList
          notices={[
            {
              message: 'Loading.',
              type: 'info',
            },
          ]}
        />
      );
    }

    return (
      <React.Fragment>
        {log === null ? (
          <ImporterLogArchive id={id} onSetLog={this.setLog} />
        ) : (
          <ImporterLogSingle id={id} log={log} onSetLog={this.setLog} />
        )}
      </React.Fragment>
    );
  }
}

ImporterLogs.propTypes = {
  id: PropTypes.number,
  location: PropTypes.object,
  history: PropTypes.object,
};

export default withRouter(ImporterLogs);
