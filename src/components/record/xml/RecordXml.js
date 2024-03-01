import React, { Component } from "react";
import PropTypes from "prop-types";
import debounce from "lodash.debounce";

import { importer } from "../../../services/importer.service";

class RecordXml extends Component {
  constructor(props) {
    super(props);

    this.state = {
      loading: true,
      record: null,
    };

    this.getPreview = debounce(this.getPreview, 300);
  }

  displayNodeClick(content, xpath = "") {
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

  displayNodeAttributes(attributes) {
    return (
      <React.Fragment>
        {typeof attributes === "object" &&
          attributes.map((attribute) => (
            <React.Fragment key={attribute.name}>
              {" "}
              {this.displayNodeClick(
                attribute.name + '="' + attribute.value + '"',
                "{" + attribute.xpath + "}"
              )}
            </React.Fragment>
          ))}
      </React.Fragment>
    );
  }

  displayNode(currentNode) {
    const node_name = currentNode.node;
    const node_xpath = currentNode.xpath ? "{" + currentNode.xpath + "}" : "";

    if (currentNode.type === "text") {
      return <li>{this.displayNodeClick(currentNode.value, node_xpath)}</li>;
    }

    return (
      <li>
        {this.displayNodeClick("&lt;" + node_name, node_xpath)}
        {this.displayNodeAttributes(currentNode.attr)}
        {this.displayNodeClick("&gt;", node_xpath)}
        {typeof currentNode.value === "object" ? (
          <ul
            className={
              Object.keys(currentNode.value).length === 1 &&
                currentNode.value["0"] &&
                currentNode.value["0"].type
                ? "iwp-preview__" + currentNode.value["0"].type
                : ""
            }
          >
            {currentNode.value.map((node, i) => (
              <React.Fragment key={i}>{this.displayNode(node)}</React.Fragment>
            ))}
          </ul>
        ) : (
          this.displayNodeClick(currentNode.value, node_xpath)
        )}
        {this.displayNodeClick("&lt;/" + node_name + "&gt;</li>", node_xpath)}
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
      this.setState({ loading: false });
    }
  }

  componentDidMount() {
    this.getPreview();
  }

  componentDidUpdate(prevProps) {
    let reload = false;

    if (prevProps.base_path !== this.props.base_path) {
      reload = true;
    }

    if (reload) {
      this.setState({ loading: true });
      this.getPreview();
    }
  }

  render() {
    const { loading, record } = this.state;
    const output = record
      ? this.displayNode(record)
      : "No data to preview, please try changing the base_path.";

    return (
      <div className="iwp-preview iwp-preview--xml">
        {loading ? "Loading" : <ul>{output}</ul>}
      </div>
    );
  }
}

RecordXml.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  base_path: PropTypes.string,
  onError: PropTypes.func,
};

RecordXml.defaultProps = {
  onSelect: () => { },
  onError: () => { },
};

export default RecordXml;
