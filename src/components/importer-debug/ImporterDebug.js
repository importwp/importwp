import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../services/importer.service';

class ImporterDebug extends Component {
  constructor(props) {
    super(props);

    this.state = {
      log: '',
      isLoading: true,
      hasMore: true,
      page: 0,
      download: ''
    };

    this.getLog = this.getLog.bind(this);
  }

  getLog() {
    const page = this.state.page + 1;
    this.setState({ isLoading: true, page: page });
    const { id } = this.props;
    importer.debug_log(id, page).then(data => {
      const { log } = this.state;
      const hasMore = data.log.length > 0;
      this.setState(
        {
          log: log + (page > 1 ? '\n' : '') + data.log.join('\n'),
          status: data.status,
          isLoading: false,
          hasMore: hasMore,
          download: data.download
        },
        () => {
          if (hasMore) {
            this.getLog();
          }
        }
      );
    });
  }

  componentDidMount() {
    this.setState({ page: 0 });
    this.getLog();

    // const node = this.scrollBox;
    // if (node) {
    //   node.addEventListener(
    //     'scroll',
    //     debounce(() => {
    //       window.requestAnimationFrame(() => {
    //         if (
    //           node.scrollTop > node.scrollHeight - node.clientHeight * 2 &&
    //           this.state.isLoading === false &&
    //           this.state.hasMore
    //         ) {
    //           this.getLog();
    //         }
    //       });
    //     }, 100)
    //   );
    // }
  }

  componentWillUnmount() {
    importer.abort('debug_log');
  }

  render() {
    return (
      <div className="iwp-form iwp-form--mb">
        <p className="iwp-heading">Debug</p>
        {this.props.settings && (
          <React.Fragment>
            <p>Importer Settings:</p>
            <textarea
              disabled
              className="iwp-debug__code iwp-debug__code--settings"
              defaultValue={this.props.settings}
            ></textarea>
          </React.Fragment>
        )}
        <React.Fragment>
          <p>
            Import Logs: (
            <a
              href={this.state.download}
              target="_blank"
              rel="noopener noreferrer"
            >
              download
            </a>
            )
          </p>
          <textarea
            ref={scrollBox => (this.scrollBox = scrollBox)}
            disabled
            className="iwp-debug__code iwp-debug__code--log"
            defaultValue={this.state.log}
          ></textarea>
        </React.Fragment>
      </div>
    );
  }
}

ImporterDebug.propTypes = {
  id: PropTypes.number,
  settings: PropTypes.string,
  log: PropTypes.string
};

ImporterDebug.defaultProps = {};

export default ImporterDebug;
