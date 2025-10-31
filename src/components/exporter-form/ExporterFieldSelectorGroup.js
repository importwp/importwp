import React from 'react';
import ExporterFieldSelectorRow from './ExporterFieldSelectorRow';

const ExporterFieldSelectorGroup = ({ fileType, fields, parent, rows, addRow, removeRow, updateRow, loop = '', row: tmp }) => {

    const children = fileType !== 'csv' ? rows.filter(item => item.parent == parent) : rows;
    const row = rows.find(item => item.id == parent);
    const nextLoop = (row?.loop === true || row?.loop === "true") ? row?.selection : loop;

    let rowFields = { ...fields };
    let groupFields = { ...fields };

    if (fileType !== 'csv') {
        // nested data structures

        // if (tmp?.parent?.length > 0) {
        //     const tmpParent = rows.find(item => item.id == tmp.parent);
        //     if (tmpParent?.loop === true || tmpParent?.loop === "true") {
        //         console.log('t1', tmp, tmpParent);

        //         const tmpFields = fields.children[row.selection];
        //         if (tmpFields && loop == row.selection) {
        //             console.log('t1', row, loop, tmpFields);
        //             if (tmpFields?.loop_fields?.length > 0) {
        //                 tmpFields.fields = [...tmpFields.loop_fields];
        //             }

        //             rowFields = { ...tmpFields }
        //             groupFields = rowFields;
        //         }
        //     }
        // }

        // data read from json is not correct
        if (row?.loop === true || row?.loop === "true") {

            // we have row set
            if (row?.selection == fields?.children[row?.selection]?.key) {
                const tmpFields = { ...fields.children[row.selection] };
                if (tmpFields?.loop_fields?.length > 0) {
                    tmpFields.fields = [...tmpFields.loop_fields];
                }
                rowFields = { ...tmpFields }
                groupFields = rowFields;
            }

        } else {
            if (loop.length == 0) {
                rowFields = { ...fields, fields: [], children: {} };

            }
        }
    }

    let show_button = false;
    if (parent == 0) {
        show_button = true;
    } else if (children.length > 0) {
        show_button = true;
    }

    return <div className='iwp-field-selector-group'>
        <div className='iwp-field-selector-children'>
            {children.map(item => {

                return <div key={item.id}>
                    <ExporterFieldSelectorRow updateRow={updateRow} fileType={fileType} fields={rowFields} addRow={addRow} row={item} removeRow={removeRow} loop={nextLoop} />
                    {fileType !== 'csv' && <ExporterFieldSelectorGroup updateRow={updateRow} fileType={fileType} fields={groupFields} row={item} rows={rows} parent={item.id} addRow={addRow} removeRow={removeRow} loop={nextLoop} />}
                </div>;
            })}
        </div>

        {show_button && <button type='button' onClick={() => addRow({ parent })} className='button button-secondary iwp-field-selector-button'>Add Field</button>}
    </div>;
};

export default ExporterFieldSelectorGroup;