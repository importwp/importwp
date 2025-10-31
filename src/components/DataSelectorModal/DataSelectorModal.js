import React from 'react';
import { useSelector } from "react-redux";
import Modal from "../modal/Modal";
import DataSelector from "../data-selector/DataSelector";

export default function DataSelectorModal({
    onClose = () => { },
    selection = '',
    subPath = '',
    visible = false
}) {

    if (!visible) {
        return;
    }

    const { id, settings, parser } = useSelector((state) => ({
        id: state.importer.importer?.id,
        settings: state.importer.importer?.file.settings,
        parser: state.importer.importer?.parser,
    }));

    let title = 'Data Selector';

    switch (parser) {
        case 'csv':
            title = 'CSV Data Selector';
            break;
        case 'xml':
            title = 'XML Data Selector';
            break;
    }

    const hideModal = () => {
        onClose(null);
    }


    const setAndClose = (value) => {
        onClose(value);
    };

    return (
        <Modal
            onClose={hideModal}
            show={visible}
            title={title}
        >
            {id && <DataSelector
                onSelect={setAndClose}
                id={id}
                parser={parser}
                settings={settings}
                selection={selection}
                subPath={subPath}
            ></DataSelector>}
        </Modal>
    );
}