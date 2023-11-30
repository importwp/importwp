import React, { useState } from 'react';
import FieldLabel from '../field-label/FieldLabel';
import PreviewRecord from './PreviewRecord';

import { importer } from '../../services/importer.service';

const PreviewForm = ({ id, parser, settings = {}, complete = () => { }, onError = () => { } }) => {

    const save = (callback = () => { }) => {
        // setDisabled(true);
        // setSaving(true);

        importer
            .save({
                id: id,
                file_settings_setup: true
            })
            .then(() => {
                setSaving(false);
                callback();
            })
            .catch((error) => {
                onError(error);
                setSaving(false);
            });
    }

    const onSave = () => {
        save();
    };

    const onSubmit = () => {
        save(() => complete());
    };

    const [disabled, setDisabled] = useState(false);
    const [saving, setSaving] = useState(false);

    return <>
        <div className="iwp-form">
            <form>
                <p className="iwp-heading iwp-heading--has-tooltip">File Settings. <a href="https://www.importwp.com/docs/importer-file-settings/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>
                <p>
                    Configure how the importer reads a record from your file, a
                    preview showing the first record is available at the bottom of the
                    page.
                </p>

                <div className="iwp-form__row">
                    <FieldLabel label='Record Preview' />
                    <PreviewRecord id={id} parser={parser} />
                </div>
            </form>
        </div>
        <div className="iwp-form__actions">
            <div className="iwp-buttons">
                <button
                    className="button button-secondary"
                    type="button"
                    onClick={onSave}
                    disabled={disabled}
                >
                    {saving && <span className="spinner is-active"></span>}
                    {saving ? 'Saving' : 'Save'}
                </button>{' '}
                <button
                    className="button button-primary"
                    type="button"
                    onClick={onSubmit}
                    disabled={disabled}
                >
                    {saving && <span className="spinner is-active"></span>}
                    {saving ? 'Saving' : 'Save & Continue'}
                </button>
            </div>
        </div>
    </>;

};

export default PreviewForm;