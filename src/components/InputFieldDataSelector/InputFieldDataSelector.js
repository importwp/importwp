import { useState } from 'react';
import DataSelectorModal from "../DataSelectorModal/DataSelectorModal";

export default function InputFieldSelector({
    value = '',
    onClose = () => { },
}) {

    const [visible, setVisible] = useState(false);

    return (
        <>
            <DataSelectorModal
                selection={value}
                onClose={(selection) => {
                    onClose(selection);
                    setVisible(false);
                }}
                visible={visible}
            />
            <button
                className="iwp-field__select"
                type="button"
                onClick={() => setVisible(true)}
            >
                Select
            </button>
        </>
    )
}