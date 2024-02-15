import { useState } from 'react';
import DataSelectorModal from "../DataSelectorModal/DataSelectorModal";
import InputButton from '../InputButton/InputButton';

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
            <InputButton
                className="iwp-field__select"
                onClick={() => setVisible(true)}
            >
                Select
            </InputButton>
        </>
    )
}