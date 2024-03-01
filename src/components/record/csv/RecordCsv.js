import React, { Component } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../../services/importer.service';

class RecordCsv extends Component {
  constructor(props) {
    super(props);

    this.state = {
      loading: true,
      headings: [],
      row: [],
      error: false,
    };

    this.getPreview = debounce(this.getPreview, 300);
    this.display = this.display.bind(this);
    this.displayNodeClick = this.displayNodeClick.bind(this);
  }

  displayNodeClick(content, xpath = '') {
    return (
      <span title={xpath} onClick={() => this.props.onSelect(xpath)}>
        {content.length > 0 ? content : <>&nbsp;</>}
      </span>
    );
  }

  display() {
    const { show_headings } = this.props;
    const { headings, row, error } = this.state;

    if (error) {
      return (
        <tbody>
          <tr>
            <td colspan="2">
              <span>Error displaying record: {error}</span>
            </td>
          </tr>
        </tbody>
      );
    }

    return (
      <tbody>
        {headings.map((heading, index) => (
          <tr key={index}>
            <th>
              {this.displayNodeClick(
                false === show_headings ? index : heading,
                '{' + index + '}'
              )}
            </th>
            <td>{this.displayNodeClick(row[index], '{' + index + '}')}</td>
          </tr>
        ))}
      </tbody>
    );
  }

  getPreview() {
    if (this.props.id && this.props.delimiter && this.props.enclosure && this.props.escape) {
      const { id } = this.props;
      const data = {
        delimiter: this.props.delimiter,
        enclosure: this.props.enclosure,
        escape: this.props.escape,
        show_headings: this.props.show_headings,
        file_encoding: this.props.file_encoding,
      };
      this.setState({ error: false });
      importer
        .filePreview(id, data)
        .then((record) => {
          if (record.headings.length == record.row.length) {
            this.setState({
              headings: record.headings,
              row: record.row,
            });
          } else {
            this.setState({
              headings: [],
              row: [],
              error: `Inconsistent num of fields, header: ${record.headings.length}, this line: ${record.row.length} `,
            });
          }
        })
        .catch((e) => {
          this.setState({ headings: [], row: [], error: e });
          this.props.onError(e);
        })
        .finally(() => {
          this.setState({ loading: false });
        });
    }
  }

  componentDidMount() {
    this.getPreview();
  }

  componentDidUpdate(prevProps) {
    let reload = false;

    if (
      prevProps.delimiter !== this.props.delimiter &&
      this.props.delimiter !== ''
    ) {
      reload = true;
    }

    if (
      prevProps.enclosure !== this.props.enclosure &&
      this.props.enclosure !== ''
    ) {
      reload = true;
    }

    if (
      prevProps.escape !== this.props.escape &&
      this.props.escape !== ''
    ) {
      reload = true;
    }

    if (prevProps.file_encoding !== this.props.file_encoding) {
      reload = true;
    }

    if (prevProps.show_headings !== this.props.show_headings) {
      reload = true;
    }

    if (reload) {
      this.setState({ loading: true });
      this.getPreview();
    }
  }

  componentWillUnmount() {
    importer.abort();
  }

  render() {
    const { show_headings } = this.props;
    const { loading } = this.state;
    const record = this.display();
    return (
      <div className="iwp-preview iwp-preview--csv">
        {loading ? (
          'Loading'
        ) : (
          <table border="1" cellPadding="0" cellSpacing="0">
            <thead>
              <tr>
                <th>
                  <span>
                    {false === show_headings ? 'Column Number' : 'Heading'}
                  </span>
                </th>
                <th>
                  <span>Value</span>
                </th>
              </tr>
            </thead>
            {record}
          </table>
        )}
      </div>
    );
  }
}

RecordCsv.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  show_headings: PropTypes.bool,
  delimiter: PropTypes.string,
  enclosure: PropTypes.string,
  onError: PropTypes.func,
  file_encoding: PropTypes.string,
};

RecordCsv.defaultProps = {
  file_encoding: '',
  show_headings: true,
  onSelect: () => { },
  onError: () => { },
};

export default RecordCsv;
