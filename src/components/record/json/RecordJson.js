import React, { Component } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../../services/importer.service';

class RecordJson extends Component {
  constructor(props) {
    super(props);

    this.state = {
      loading: true,
      record: null,
    };

    this.getPreview = debounce(this.getPreview, 300);
  }

  displayNodeClick(content, xpath = '') {
    return (
      <span
        title={xpath}
        onClick={() => this.props.onSelect(xpath)}
        dangerouslySetInnerHTML={{
          __html: content,
        }}
      ></span>
    );
  }

  displayNode(currentNode) {
    const node_name = currentNode.node;
    const node_xpath = currentNode.xpath ? '{' + currentNode.xpath + '}' : '';

    if (currentNode.type === 'text') {
      return <li>{this.displayNodeClick(currentNode.value, node_xpath)}</li>;
    }

    const hasChildren = Array.isArray(currentNode.value);

    return (
      <li>
        {this.displayNodeClick('"' + node_name + '"', node_xpath)}
        {hasChildren ? (
          <React.Fragment>
            {this.displayNodeClick(': {', node_xpath)}
            {currentNode.value.length > 0 ? (
              <ul>
                {currentNode.value.map((node, i) => (
                  <React.Fragment key={i}>{this.displayNode(node)}</React.Fragment>
                ))}
              </ul>
            ) : null}
            {this.displayNodeClick('}', node_xpath)}
          </React.Fragment>
        ) : (
          <React.Fragment>
            {this.displayNodeClick(': ', node_xpath)}
            {this.displayNodeClick(
              typeof currentNode.value === 'string'
                ? '"' + currentNode.value + '"'
                : String(currentNode.value),
              node_xpath
            )}
          </React.Fragment>
        )}
      </li>
    );
  }

  getPreview() {
    if (this.props.id && this.props.base_path) {
      this.setState({ loading: true });

      const { id } = this.props;
      const data = {
        base_path: this.props.base_path,
      };
      importer
        .filePreview(id, data)
        .then((record) => {
          this.setState({
            record: record,
          });
        })
        .catch((e) => this.props.onError(e))
        .finally(() => {
          this.setState({ loading: false });
        });
    } else {
      this.setState({ loading: false, record: null });
    }
  }

  componentDidMount() {
    this.getPreview();
  }

  componentDidUpdate(prevProps) {
    if (prevProps.base_path !== this.props.base_path) {
      this.setState({ loading: true });
      this.getPreview();
    }
  }

  render() {
    const { loading, record } = this.state;
    const output = record
      ? this.displayNode(record)
      : 'No data to preview, please try changing the base_path.';

    return (
      <div className="iwp-preview iwp-preview--json">
        {loading ? 'Loading' : <ul>{output}</ul>}
      </div>
    );
  }
}

RecordJson.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  base_path: PropTypes.string,
  onError: PropTypes.func,
};

RecordJson.defaultProps = {
  onSelect: () => {},
  onError: () => {},
};

export default RecordJson;
