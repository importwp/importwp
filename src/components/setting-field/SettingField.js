import React, { Component } from 'react';
import PropTypes from 'prop-types';

class SettingField extends Component {
  render() {
    const { value, onChange, label, id, type } = this.props;

    if (type === 'checkbox') {
      return (
        <div className="iwp-form__row">
          <label className="iwp-form__label">
            <input
              type="checkbox"
              className="iwp-form__input"
              name={'setting_' + id}
              onChange={onChange}
              checked={value}
              value="yes"
            />
            {label}
          </label>
        </div>
      );
    }

    return (
      <div className="iwp-form__row">
        <label className="iwp-form__label">{label}</label>
        <input
          type="text"
          className="iwp-form__input"
          name={'setting_' + id}
          onChange={onChange}
          value={value}
        />
      </div>
    );
  }
}

SettingField.propTypes = {
  value: PropTypes.any,
  onChange: PropTypes.func,
  label: PropTypes.string,
  id: PropTypes.number,
  type: PropTypes.string
};

SettingField.defaultProps = {
  onChange: () => {},
  value: ''
};

export default SettingField;
