import React, { useCallback, useImperativeHandle, useState, forwardRef } from 'react';
import PropTypes from 'prop-types';
import { importer } from '../../../services/importer.service';

const ExistingDatasource = forwardRef(function ExistingDatasource({
    id,
    files,
    file: fileProp,
    onError = () => { }
}, ref) {
    const [file, setFile] = useState(fileProp);
    const [prevFileProp, setPrevFileProp] = useState(fileProp);

    if (fileProp !== prevFileProp) {
        setPrevFileProp(fileProp);
        setFile(fileProp);
    }

    const onChange = (event) => {
        setFile(event.target.value);
    };

    const run = useCallback((callback = () => { }) => {
        importer.save({ id, existing_id: file }).then(() => {
            callback();
        }, error => {
            onError(error);
        });
    }, [id, file, onError]);

    useImperativeHandle(ref, () => ({
        run
    }), [run]);

    return (
        <React.Fragment>
            {files && Object.keys(files).length > 0 && (
                <div className="iwp-file-list">
                    <ul>
                        {Object.keys(files).map(file_id => (
                            <li key={file_id}>
                                <input
                                    id={'file_' + file_id}
                                    type="radio"
                                    name="file"
                                    value={file_id}
                                    onChange={onChange}
                                    checked={file_id == file}
                                />
                                <label htmlFor={'file_' + file_id}>
                                    {files[file_id]}
                                </label>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </React.Fragment>
    );
});

ExistingDatasource.propTypes = {
    id: PropTypes.number,
    files: PropTypes.array,
    file: PropTypes.number,
    onError: PropTypes.func
};

export default ExistingDatasource;
