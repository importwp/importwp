import React, { useEffect, useState } from 'react';
import FieldLabel from '../field-label/FieldLabel';
import ExporterFieldSelectorGroup from './ExporterFieldSelectorGroup';

import './ExporterFieldSelector.scss';

const ExporterFieldSelector = ({ fields, fileType, activeType, setFields }) => {

    const [lastId, setLastId] = useState(0);

    useEffect(() => {
        setLastId(fields.reduce((carry, item) => carry < +item.id ? +item.id : carry, 0));
    }, [fields]);

    const row = fields.find(item => item.id == 0);

    return <div className="iwp-form__row">
        <FieldLabel
            id="fields"
            field="fields"
            label="Fields to export"
            display="inline-block"
            tooltip="Leave empty to export all fields"
        />

        <ExporterFieldSelectorGroup fileType={fileType} fields={activeType.fields} row={row} rows={fields} parent={0} addRow={(data) => {
            setFields([...fields, { ...data, id: lastId + 1 }]);
        }
        } removeRow={(id) => {

            const target = fields.find(item => item.id == id);

            setFields([...fields.filter(item => item.id !== id).map(item => {
                if (item.parent == id) {
                    item = {
                        ...item,
                        parent: target?.parent
                    };
                }

                return item;
            })]);
        }} updateRow={(id, data) => {

            setFields([...fields.map(item => {

                if (item.id == id) {
                    item = {
                        ...item,
                        ...data
                    };
                }

                return item;
            })])

        }} />
    </div>;
};

export default ExporterFieldSelector;