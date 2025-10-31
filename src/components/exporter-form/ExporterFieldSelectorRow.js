import React from 'react';
import CreatableSelect from 'react-select/creatable';
import FieldLabel from '../field-label/FieldLabel';

const customReactSelectStyles = {
    control: (provided, state) => ({
        ...provided,
        background: '#fff',
        borderColor: '#7e8993',
        minHeight: '30px',
        height: '30px',
        boxShadow: state.isFocused ? null : null,
        borderRadius: 0
    }),

    valueContainer: (provided, state) => ({
        ...provided,
        height: '30px',
        padding: '0 6px'
    }),

    input: (provided, state) => ({
        ...provided,
        margin: '0px',
    }),
    indicatorSeparator: state => ({
        display: 'none',
    }),
    indicatorsContainer: (provided, state) => ({
        ...provided,
        height: '30px',
    }),
};

const ExporterFieldSelectorRow = ({ fileType, fields, row, addRow, removeRow, updateRow, loop = '' }) => {

    const onFieldChange = (data) => {

        const custom = data?.__isNew__ == true || data?.type == 'custom';

        if (!data) {
            updateRow(row.id, {
                selection: '',
                loop: false,
                custom
            });
        } else {
            updateRow(row.id, {
                selection: data && data.value ? data.value : '',
                loop: data.type == 'loop',
                custom
            });
        }

    };

    const flattenFieldOptions = (items, prefix = '') => {

        let tmp = items.fields.map((option) => {
            return { value: `${prefix}${option}`, label: `${prefix}${option}`, type: 'field' };
        });

        if (fileType !== 'csv' && items.loop) {
            tmp = [...tmp, {
                label: `${items.label} Loop`,
                value: `${items.key}`,
                type: 'loop'
            }];
        }

        if (items?.children) {

            for (const [key, value] of Object.entries(items.children)) {
                tmp = [...tmp, ...flattenFieldOptions(value, prefix.length > 0 ? `${prefix}.${key}.` : `${key}.`)];
            }
        }

        return tmp;
    };

    const groupedFieldOptions = (items, prefix = '', depth = 0) => {

        const data = {
            label: items.label,
            options: items.fields.map((option) => {
                return { value: `${prefix}${option}`, label: `${option}`, type: 'field' };
            })
        };


        // if (items.length == 1 || items.loop && depth > 0) {
        if (fileType !== 'csv' && items.loop && (items.fields.length == 0 || depth > 0)) {
            data.options = [{
                label: `${items.label} Loop`,
                value: `${items.key}`,
                type: 'loop'
            }, ...data.options];
        }

        let tmp = [data];

        if (items?.children) {

            for (const [key, value] of Object.entries(items.children)) {
                tmp = [...tmp, ...groupedFieldOptions(value, prefix.length > 0 ? `${prefix}.${key}.` : `${key}.`, depth + 1)];
            }
        }

        return tmp;
    };

    const flatFieldOptions = flattenFieldOptions(fields);
    const fieldOptions = groupedFieldOptions(fields);

    let found = flatFieldOptions.find(item => item.value == row?.selection);
    if (!found && row?.selection?.length > 0) {
        found = {
            value: row.selection,
            label: row.selection,
            type: 'custom'
        };
    }

    return <div className='iwp-field-selector-item'>
        <div className='iwp-field-selector-grid'>
            <div className='iwp-field-selector-col iwp-field-selector-col--flex iwp-field-selector-col--text'>
                <FieldLabel id={`ewp-field-label-${row.id}`} label="Label" tooltip="Leave label empty to use the data selector." field={`ewp-field-label-${row.id}-input`} />
                <input id={`ewp-field-label-${row.id}-input`} type="text" value={row?.label ? row.label : ''} className='iwp-form__input' onChange={(event) => updateRow(row.id, {
                    label: event.target.value
                })} />
            </div>
            <div className='iwp-field-selector-col iwp-field-selector-col--flex iwp-field-selector-col--fields'>
                <FieldLabel id={`ewp-field-data-${row.id}`} label="Data" tooltip="Select which part of the record to populate" field={`ewp-field-label-${row.id}-select`} />
                <div>
                    <CreatableSelect
                        id={`ewp-field-label-${row.id}-select`}
                        isClearable
                        options={fieldOptions}
                        value={found}
                        onChange={onFieldChange}
                        styles={customReactSelectStyles}
                        className='iwp-form__select'
                    />
                </div>
            </div>
            {(fileType == 'xml' || fileType == 'json') && <div className='iwp-field-selector-col iwp-field-selector-col--action'>
                <button type='button' onClick={() => addRow({ parent: row.id })} className='button button-secondary'>Add Sub Field</button>
            </div>}
            <div className='iwp-field-selector-col iwp-field-selector-col--action'>
                <button type='button' onClick={() => removeRow(row.id)} className='button button-secondary'>Delete</button>
            </div>
        </div>
    </div >;
};

export default ExporterFieldSelectorRow;