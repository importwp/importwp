import React, { useState } from 'react';
import PropTypes from 'prop-types';
import './EditSteps.scss';
import { importer as ImporterService } from '../../services/importer.service';

const EditSteps = ({
  id,
  step,
  maxStep,
  importer,
  gotoStep = () => {},
  onError = () => {},
}) => {
  const { name, parser } = importer;
  let { template } = importer;

  const [isEditingName, setEditingName] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editName, setEditName] = useState(name);

  // TODO: remove duplication of this code with ImporterListItem
  if (template === 'custom-post-type') {
    template = 'Custom Post Type: ' + importer.settings.post_type;
  } else if (template === 'term') {
    template = 'Taxonomy: ' + importer.settings.taxonomy;
  }

  const saveNameChange = () => {
    if (editName.length < 1) {
      onError('Importer name must be 1 character minimum.');
      return;
    }

    setSaving(true);
    ImporterService.save({ id, name: editName }).then(
      () => {
        setEditingName(false);
        setSaving(false);
      },
      (error) => {
        setSaving(false);
        onError(error);
      }
    );
  };

  return (
    <React.Fragment>
      {step > -1 && (
        <p className="iwp-importer-header">
          {isEditingName ? (
            <>
              <input
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    saveNameChange();
                  }
                }}
                style={{ height: '30px' }}
              />
              <button
                type="button"
                className="button button-secondary"
                onClick={saveNameChange}
              >
                {saving && <span className="spinner is-active"></span>}
                {saving ? 'Saving' : 'Save'}
              </button>
              <button
                type="button"
                className="button button-link-delete"
                onClick={() => {
                  setEditingName(false);
                  setEditName(name);
                }}
              >
                Cancel
              </button>
            </>
          ) : (
            <span
              onClick={() => setEditingName((state) => !state)}
              style={{ cursor: 'pointer' }}
              title="Click to edit name"
            >
              {name}
            </span>
          )}
          <small>
            Importing <strong>{template}</strong> from <strong>{parser}</strong>
            .
          </small>
        </p>
      )}
      <ul className="iwp-steps">
        <li
          className={
            'iwp-step' +
            (step === 0 ? ' iwp-step--active' : '') +
            (maxStep >= 0 ? ' iwp-step--complete' : '')
          }
          onClick={() => maxStep >= 0 && gotoStep(0)}
        >
          1. Select File
        </li>
        <li
          className={
            'iwp-step' +
            (step === 1 ? ' iwp-step--active' : '') +
            (maxStep >= 1 ? ' iwp-step--complete' : '')
          }
          onClick={() => maxStep >= 1 && gotoStep(1)}
        >
          2. File Settings
        </li>
        <li
          className={
            'iwp-step' +
            (step === 2 ? ' iwp-step--active' : '') +
            (maxStep >= 2 ? ' iwp-step--complete' : '')
          }
          onClick={() => maxStep >= 2 && gotoStep(2)}
        >
          3. Template Fields
        </li>
        <li
          className={
            'iwp-step' +
            (step === 3 ? ' iwp-step--active' : '') +
            (maxStep >= 3 ? ' iwp-step--complete' : '')
          }
          onClick={() => maxStep >= 3 && gotoStep(3)}
        >
          4. Permissions
        </li>
        <li
          className={
            'iwp-step' +
            (step === 4 ? ' iwp-step--active' : '') +
            (maxStep >= 4 ? ' iwp-step--complete' : '')
          }
          onClick={() => maxStep >= 4 && gotoStep(4)}
        >
          5. Run Import
        </li>
        <li
          className={
            'iwp-step' +
            (step === 5 ? ' iwp-step--active' : '') +
            (maxStep >= 4 ? ' iwp-step--complete' : '')
          }
          onClick={() => maxStep >= 4 && gotoStep(5)}
        >
          6. History
        </li>
      </ul>
    </React.Fragment>
  );
};

EditSteps.propTypes = {
  gotoStep: PropTypes.func,
  step: PropTypes.number,
  maxStep: PropTypes.number,
  importer: PropTypes.object,
  form: PropTypes.string,
};

EditSteps.defaultProps = {
  gotoStep: () => {},
};

export default EditSteps;
