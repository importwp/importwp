import PropTypes from 'prop-types';
import { Tooltip } from 'react-tooltip';

const FieldLabel = ({ tooltip, label, id, field = '', display }) => {
  let field_class = '';
  if (display === 'inline-block') {
    field_class = 'iwp-label--inline-block';
  }

  return (
    <>
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
    </>
  );
};

FieldLabel.propTypes = {
  field: PropTypes.string,
  display: PropTypes.string,
  label: PropTypes.string.isRequired,
  tooltip: PropTypes.any,
  id: PropTypes.string
};

export default FieldLabel;
