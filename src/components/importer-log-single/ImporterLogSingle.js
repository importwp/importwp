import React, { Component } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../services/importer.service';
import StatusMessageOld from '../status-message/StatusMessageOld';

const colStyles = (index) => {
  let width = '60%';
  switch (index) {
    case 0:
      width = '20%';
      break;
    case 1:
      width = '80%';
      break;
  }
  return {
    width: width,
  };
};

class ImporterLogSingle extends Component {
  constructor(props) {
    super(props);

    this.state = {
      logs: [],
      status: null,
      isLoading: true,
      hasMore: true,
      page: 0,
    };

    this.getLog = this.getLog.bind(this);
  }

  getLog() {
    const page = this.state.page + 1;
    this.setState({ isLoading: true, page: page });
    const { id, log } = this.props;
    importer.log(id, log, page).then((data) => {
      const { logs } = this.state;

      this.setState({
        logs: logs.concat(data.logs),
        status: data.status,
        isLoading: false,
        hasMore: data.logs.length > 0,
      });
    });
  }

  componentDidMount() {
    this.setState({ page: 0 });
    this.getLog();

    const node = this.scrollBox;
    if (node) {
      node.addEventListener(
        'scroll',
        debounce(() => {
          window.requestAnimationFrame(() => {
            // console.log(node.scrollTop, node.scrollHeight - node.clientHeight);

            if (
              node.scrollTop > node.scrollHeight - node.clientHeight * 2 &&
              this.state.isLoading === false &&
              this.state.hasMore
            ) {
              this.getLog();
            }

            // TODO: if we are close to the end, load the next page
            // this.getLog();
          });
        }, 100)
      );
    }
  }

  componentWillUnmount() {
    importer.abort('log');
  }

  render() {
    const { log } = this.props;
    const { logs, status, isLoading } = this.state;
    return (
      <React.Fragment>
        <div className="iwp-form">
          <p className="iwp-heading">
            Importer History: <small>{log}</small>{' '}
            <button
              type="button"
              className="button button-secondary button-small"
              onClick={() => this.props.onSetLog(null)}
            >
              Back
            </button>
          </p>

          {status && (
            <div className="iwp-notices">
              <div className="iwp-mb-20 iwp-notice iwp-notice--info iwp-notice--bordered">
                <p>
                  <StatusMessageOld showStatus={true} status={status} />
                </p>
              </div>
            </div>
          )}

          <div className="iwp-table__wrapper">
            <table className="iwp-table iwp-table--fixed iwp-table--logs">
              <thead>
                <tr>
                  <th style={colStyles(0)}>Record</th>
                  <th style={colStyles(1)}>Message</th>
                </tr>
              </thead>
              <tbody ref={(scrollBox) => (this.scrollBox = scrollBox)}>
                {logs.map((log) => (
                  <tr key={log[0] + log[1]}>
                    <td className="iwp-table-row">{log[0]}</td>
                    <td className="iwp-table-content">{log[2]}</td>
                  </tr>
                ))}
                {isLoading && (
                  <tr>
                    <td colSpan={2}>Loading...</td>
                  </tr>
                )}
                {logs.length === 0 && !isLoading && (
                  <tr>
                    <td colSpan={2}>No Logs found.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </React.Fragment>
    );
  }
}

ImporterLogSingle.propTypes = {
  id: PropTypes.number.isRequired,
  log: PropTypes.string.isRequired,
  onSetLog: PropTypes.func,
};

export default ImporterLogSingle;
