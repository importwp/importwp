import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { getFieldMap, setTemplate } from '../../features/importer/importerSlice';

const FieldSerialized = function ({
  field,
  name,
  showSelectModal,
}) {

  const map = useSelector((state) => getFieldMap(state, name));
  const dispatch = useDispatch();

  const index_key = `${name}.${field.id}._index`;
  const index = map.hasOwnProperty(index_key)
    ? +map[index_key]
    : 0;

  const onChangeHandler = (event) => {
    const target = event.target;
    let value = target.value;
    dispatch(setTemplate({ [target.name]: value }))
  };

  const removeRowHandler = (index) => {
    const prefix = `${name}._serialized`;
    const rowCount = map[`${prefix}._index`];
    const newRowCount = rowCount - 1;

    let data = {};

    for (let i = index; i < newRowCount; i++) {
      data = {
        ...data,
        [`${prefix}.${i}.key`]: map[`${name}._serialized.${i + 1}.key`] || '',
        [`${prefix}.${i}.value`]: map[`${name}._serialized.${i + 1}.value`] || '',
      };
    }

    data[`${prefix}.${newRowCount}.key`] = '';
    data[`${prefix}.${newRowCount}.value`] = '';

    data[`${prefix}._index`] = newRowCount;

    dispatch(setTemplate(data));
  };

  const renderRows = function () {
    let output = [];

    for (let i = 0; i < index; i++) {
      output.push(
        <tr key={`${name}._serialized.${i}`}>
          <td>
            <div className="iwp-field__input-wrapper">
              <input
                type="text"
                name={`${name}._serialized.${i}.key`}
                id={`${name}._serialized.${i}.key`}
                onChange={onChangeHandler}
                value={getValue(`_serialized.${i}.key`)}
              />
              <button
                type="button"
                onClick={() =>
                  showSelectModal(
                    `${name}._serialized.${i}.key`,
                    map.hasOwnProperty('row_base') ? map.row_base : ''
                  )
                }
              >
                Select
              </button>
            </div>
          </td>
          <td>
            <div className="iwp-field__input-wrapper">
              <input
                type="text"
                name={`${name}._serialized.${i}.value`}
                id={`${name}._serialized.${i}.value`}
                onChange={onChangeHandler}
                value={getValue(`_serialized.${i}.value`)}
              />
              <button
                type="button"
                onClick={() =>
                  showSelectModal(
                    `${name}._serialized.${i}.value`,
                    map.hasOwnProperty('row_base') ? map.row_base : ''
                  )
                }
              >
                Select
              </button>
            </div>
          </td>
          <td>
            <button
              type="button"
              onClick={() => removeRowHandler(i)}
              className="button button-secondary"
            >
              -
            </button>
          </td>
        </tr>
      );
    }

    return output;
  };

  const getValue = function (key) {
    const index_key = `${name}.${key}`;
    return map.hasOwnProperty(index_key) ? map[index_key] : '';
  };

  return (
    <>
      <table>
        <tbody>{renderRows()}</tbody>
        {index > 0 && (
          <thead>
            <tr>
              <th>Key</th>
              <th>Value</th>
              <th></th>
            </tr>
          </thead>
        )}
        <tfoot>
          <tr>
            <td colSpan={3}>
              <button
                type="button"
                onClick={() => {
                  dispatch(setTemplate({ [`${name}._serialized._index`]: index + 1 }))
                }}
                className="button button-secondary"
              >
                Add row
              </button>
            </td>
          </tr>
        </tfoot>
      </table>
    </>
  );
};

export default FieldSerialized;
