import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Tooltip } from 'react-tooltip';

class FieldLabel extends Component {
  render() {
    const { tooltip, label, id, field, display } = this.props;

    let field_class = '';
    if (display === 'inline-block') {
      field_class = 'iwp-label--inline-block';
    }

    return (
      <React.Fragment>
        {tooltip ? (
          <label className={'iwp-form__label iwp-label--has-tooltip ' + field_class} htmlFor={field}>
            {label}:
            <span className="iwp-label__tooltip" data-tooltip-content={tooltip} data-tooltip-id={'iwp-tooltip_' + id}>
              ?
            </span>
            <Tooltip id={'iwp-tooltip_' + id} effect="solid" delayHide={300} className="iwp-react-tooltip" />
          </label>
        ) : (
          <label htmlFor={field} className={'iwp-form__label ' + field_class}>{label}:</label>
        )}
      </React.Fragment>
    );
  }
}

FieldLabel.propTypes = {
  field: PropTypes.string,
  display: PropTypes.string,
  label: PropTypes.string.isRequired,
  tooltip: PropTypes.any,
  id: PropTypes.string
};

FieldLabel.defaultProps = {
  field: ''
};

export default FieldLabel;
