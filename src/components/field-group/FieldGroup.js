import React from 'react';
import PropTypes from 'prop-types';
import FieldSet from '../field-set/FieldSet';
import { connect } from 'react-redux';
import Switch from 'react-switch';
import {
  getEnabledMap,
  getFieldMap,
  setEnabled,
  setPreview,
  setTemplate,
} from '../../features/importer/importerSlice';

class FieldGroup extends React.PureComponent {
  constructor(props) {
    super(props);

    this.state = {
      show_field_dropdown: false,
    };

    this.setWrapperRef = this.setWrapperRef.bind(this);
    this.handleClickOutside = this.handleClickOutside.bind(this);
    this.getEnableFieldLabel = this.getEnableFieldLabel.bind(this);
  }

  componentDidMount() {
    document.addEventListener('mousedown', this.handleClickOutside);
  }

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
    const { heading, type, link, settings = [] } = this.props.group;
    const { enabledData } = this.props;

    const switch_height = 20;
    const switch_width = 40;


    return (
      <div className="iwp-form iwp-form--mb">
        <form>
          {link ? <p className="iwp-heading iwp-heading--has-tooltip">{heading} <a href={`${link}?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer`} target='_blank' className='iwp-label__tooltip'>?</a></p> : <p className="iwp-heading">{heading}</p>}

          {React.cloneElement(window.iwp.hooks.applyFilters(
            `iwp_panel_${this.props.group.id}`,
            <></>
          ), {
            ...this.props,
            setTemplate: (data) => {
              this.props.dispatch(
                setTemplate(data)
              );
            },
            setPreview: (data) => {
              this.props.dispatch(
                setPreview(data)
              );
            }
          })}

          {settings && <div className='iwp-panel-settings'>
            {settings.map(field => {

              const field_id = `${this.props.group.id}._iwp_settings.${field.id}`;
              const value = this.props.map.hasOwnProperty(field_id) ? (this.props.map[field_id] == 'yes' ? true : false) : false;

              return <div className="iwp-form__row iwp-form__row--small">
                <label className="iwp-form__label iwp-form__label--switch">
                  <span>{field.label}</span>
                  <Switch
                    checked={value}
                    name={field_id}
                    height={switch_height}
                    width={switch_width}
                    onColor="#22c48f"
                    onChange={(checked) => {
                      this.props.dispatch(
                        setTemplate({ [field_id]: checked ? 'yes' : 'no' })
                      );
                    }}
                  />
                </label>
              </div>
            })}
          </div>}

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

                          this.props.dispatch(setEnabled({ [key]: !enabledData[key] }));
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
  map: getFieldMap(state, props.group.id),
});

export default connect(mapStateToProps)(FieldGroup);
