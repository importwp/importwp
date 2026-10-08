import React, { useEffect, useRef, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';

import { importer } from '../../services/importer.service';
import {
    selectPreviewRecord,
    setPreviewRecord,
} from '../../features/importer/importerSlice';
import RecordNavigator from '../record/RecordNavigator';

const PreviewRecord = ({ id, parser, onSelect = () => { }, onError = () => { }, onRecordChange = () => { } }) => {

    const dispatch = useDispatch();
    const storedRecord = useSelector(selectPreviewRecord);
    const [loading, setLoading] = useState(true);
    const [preview, setPreview] = useState();
    const [record, setRecord] = useState(storedRecord);
    const [total, setTotal] = useState(0);
    const [paged, setPaged] = useState(false);
    const onSelectRef = useRef(onSelect);
    const onErrorRef = useRef(onError);
    const onRecordChangeRef = useRef(onRecordChange);
    onSelectRef.current = onSelect;
    onErrorRef.current = onError;
    onRecordChangeRef.current = onRecordChange;

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        importer
            .filePreview(id, { record })
            .then((response) => {
                if (cancelled) {
                    return;
                }

                const nextRecord = typeof response?.record === 'number' ? response.record : record;
                const nextTotal = typeof response?.total === 'number' ? response.total : null;

                if (nextTotal !== null) {
                    setTotal(nextTotal);
                    setPaged(true);
                }

                if (nextRecord !== record) {
                    setRecord(nextRecord);
                    dispatch(setPreviewRecord(nextRecord));
                    onRecordChangeRef.current(nextRecord);
                }

                const displayNodeClick = (content, xpath = '') => {
                    return (
                        <span title={xpath} onClick={() => onSelectRef.current(xpath)}>
                            {content && content.length > 0 ? content : <>&nbsp;</>}
                        </span>
                    );
                };

                setPreview(window.iwp.hooks.applyFilters('iwp_preview_record', undefined, response, displayNodeClick));
            })
            .catch((e) => {
                if (!cancelled) {
                    onErrorRef.current(e);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
            importer.abort('filePreview');
        };
    }, [id, record, dispatch]);

    const onNavigate = (nextRecord) => {
        if (nextRecord === record) {
            return;
        }
        setRecord(nextRecord);
        dispatch(setPreviewRecord(nextRecord));
        onRecordChangeRef.current(nextRecord);
    };

    return <div className={`iwp-preview iwp-preview--${parser}`}>
        {paged && (
            <RecordNavigator
                record={record}
                total={total}
                onChange={onNavigate}
                disabled={loading}
            />
        )}
        <div className="iwp-preview__body">
            {loading ? 'Loading...' : preview}
        </div>
    </div>;
};

export default PreviewRecord;
