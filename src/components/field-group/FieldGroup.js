import React from 'react';
import PropTypes from 'prop-types';
import FieldSet from '../field-set/FieldSet';
import { connect } from 'react-redux';
import {
  getEnabledMap,
  setEnabled,
} from '../../features/importer/importerSlice';

class FieldGroup extends React.PureComponent {
  constructor(props) {
    super(props);

    this.state = {
      show_field_dropdown: false,
      // ...enable_state
    };

    this.setWrapperRef = this.setWrapperRef.bind(this);
    this.handleClickOutside = this.handleClickOutside.bind(this);
    this.getEnableFieldLabel = this.getEnableFieldLabel.bind(this);
  }

  // componentDidUpdate(prevProps, prevState, snapshot){
  //   // console.log('componentDidUpdate', 'FieldGroup', this.props.group.id);

  //   // console.log('FieldGroup props', prevProps, this.props);
  //   // console.log('FieldGroup state', prevState, this.state);
  //   // console.log('FieldGroup snapshot', snapshot);
  // }

  componentDidMount() {
    document.addEventListener('mousedown', this.handleClickOutside);
  }

  // componentDidUpdate(){
  //   console.log(this.props.group.id, this.props);
  // }

  componentWillUnmount() {
    document.removeEventListener('mousedown', this.handleClickOutside);
  }

  /**
   * Set the wrapper ref
   */
  setWrapperRef(node) {
    this.wrapperRef = node;
  }

  /**
   * Alert if clicked on outside of element
   */
  handleClickOutside(event) {
    if (this.wrapperRef && !this.wrapperRef.contains(event.target)) {
      this.setState({ show_field_dropdown: false });
    }
  }

  /**
   * Take enable field key and get readable label
   * @param {string} key
   */
  getEnableFieldLabel(key) {
    let output = key.substring(key.lastIndexOf('.') + 1);
    if (output.indexOf('_') === 0) {
      return output.substring(1);
    }

    const field = this.props.group.fields.find((field) => field.id === output);
    if (field) {
      return field.type === 'field' ? field.label : field.heading;
    }

    return output;
  }

  render() {
    const { heading, type } = this.props.group;
    const { enabledData } = this.props;

    return (
      <div className="iwp-form iwp-form--mb">
        <form>
          <p className="iwp-heading">{heading}</p>
          <FieldSet
            id={this.props.group.id}
            group={this.props.group}
            showSelectModal={this.props.showSelectModal}
            importer_id={this.props.importer_id}
          />
        </form>
        {type !== 'repeatable' && Object.keys(enabledData).length > 0 && (
          <div className="iwp-buttons">
            <div className="iwp-dropdown" ref={this.setWrapperRef}>
              <button
                type="button"
                className="button button-secondary"
                onClick={() => {
                  this.setState({
                    show_field_dropdown: !this.state.show_field_dropdown,
                  });
                }}
              >
                Enable Fields
              </button>
              {this.state.show_field_dropdown && (
                <ul className="iwp-dropdown__menu">
                  {Object.keys(enabledData).map((key) => (
                    <li key={key} className="iwp-dropdown__item">
                      <a
                        onClick={() => {
                          this.setState({
                            show_field_dropdown: false,
                          });

                          this.props.setEnabled({ [key]: !enabledData[key] });
                        }}
                      >
                        {enabledData[key] === true ? 'Disable' : 'Enable'}:{' '}
                        {this.getEnableFieldLabel(key)}
                      </a>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        )}
      </div>
    );
  }
}

FieldGroup.propTypes = {
  group: PropTypes.object.isRequired,
  showSelectModal: PropTypes.func,
  onEnabledField: PropTypes.func,
  enabledData: PropTypes.object,
  importer_id: PropTypes.number,
};

FieldGroup.defaultProps = {
  enabledData: {},
};

const mapStateToProps = (state, props) => ({
  enabledData: getEnabledMap(state, props.group.id),
});

const mapDispatchToProps = { setEnabled };

export default connect(mapStateToProps, mapDispatchToProps)(FieldGroup);
