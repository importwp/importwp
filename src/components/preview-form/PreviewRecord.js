import React, { useEffect, useState } from 'react';

import { importer } from '../../services/importer.service';

const PreviewRecord = ({ id, parser, onSelect = () => { }, onError = () => { } }) => {

    const [loading, setLoading] = useState(true);
    const [preview, setPreview] = useState();

    const displayNodeClick = (content, xpath = '') => {
        return (
            <span title={xpath} onClick={() => onSelect(xpath)}>
                {content.length > 0 ? content : <>&nbsp;</>}
            </span>
        );
    };

    const getPreview = () => {
        setLoading(true);

        importer
            .filePreview(id)
            .then((record) => {

                setPreview(window.iwp.hooks.applyFilters('iwp_preview_record', undefined, record, displayNodeClick));
            })
            .catch((e) => {
                console.error(e);
            })
            .finally(() => {
                setLoading(false);
            });
    };

    useEffect(() => {
        getPreview();
    }, []);


    return <div className={`iwp-preview iwp-preview--${parser}`}>
        {loading ? 'Loading' : preview}
    </div>;
};

export default PreviewRecord;