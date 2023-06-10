import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  getFieldMap,
  setTemplate,
} from '../../features/importer/importerSlice';

const FieldMapped = function ({ field, name, append_field_id = true }) {
  const map = useSelector((state) => getFieldMap(state, name));
  const dispatch = useDispatch();

  const index_key = append_field_id
    ? `${name}.${field.id}._index`
    : `${name}._mapped._index`;
  const index = map.hasOwnProperty(index_key) ? +map[index_key] : 0;

  const removeRowHandler = (index) => {

    const prefix = `${name}._mapped`;
    const rowCount = map[`${prefix}._index`];
    const newRowCount = rowCount - 1;

    let data = {};

    for (let i = index; i < newRowCount; i++) {
      data = {
        ...data,
        [`${prefix}.${i}.key`]: map[`${name}._mapped.${i + 1}.key`] || '',
        [`${prefix}.${i}._condition`]:
          map[`${name}._mapped.${i + 1}._condition`] || '',
        [`${prefix}.${i}.value`]: map[`${name}._mapped.${i + 1}.value`] || '',
      };
    }

    data[`${prefix}.${newRowCount}.key`] = '';
    data[`${prefix}.${newRowCount}._condition`] = '';
    data[`${prefix}.${newRowCount}.value`] = '';

    data[`${prefix}._index`] = newRowCount;

    dispatch(setTemplate(data));
  };

  const onChange = (event) => {
    const target = event.target;
    let value = target.value;
    dispatch(setTemplate({ [target.name]: value }));
  };

  const getFieldValue = (key) => {
    const index_key = `${name}.${key}`;
    return map.hasOwnProperty(index_key) ? map[index_key] : '';
  };

  const renderRows = function () {
    let output = [];

    for (let i = 0; i < index; i++) {
      output.push(
        <tr key={`${name}._mapped.${i}`}>
          <td>
            <select
              name={`${name}._mapped.${i}._condition`}
              id={`${name}._mapped.${i}._condition`}
              value={getFieldValue(`_mapped.${i}._condition`)}
              onChange={onChange}
            >
              <option value="equal">Equals</option>
              <option value="contains">Contains</option>
              <option value="in">In</option>
              <option value="not-equal">Not Equals</option>
              <option value="not-contains">Not Contains</option>
              <option value="not-in">Not In</option>
              {!append_field_id && <>
                <option value="gt">Greater Than</option>
                <option value="gte">Greater Than or Equal</option>
                <option value="lt">Less Than</option>
                <option value="lte">Less Than or Equal</option>
              </>}
            </select>
          </td>
          <td>
            <input
              type="text"
              name={`${name}._mapped.${i}.key`}
              id={`${name}._mapped.${i}.key`}
              onChange={onChange}
              value={getFieldValue(`_mapped.${i}.key`)}
            />
          </td>
          <td>
            <input
              type="text"
              name={`${name}._mapped.${i}.value`}
              id={`${name}._mapped.${i}.value`}
              onChange={onChange}
              value={getFieldValue(`_mapped.${i}.value`)}
            />
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

  return (
    <>
      <table>
        <tbody>{renderRows()}</tbody>
        {index > 0 && (
          <thead>
            <tr>
              <th>If Value</th>
              <th>This</th>
              <th>Then Return</th>
            </tr>
          </thead>
        )}
        <tfoot>
          <tr>
            <td colSpan={4}>
              <button
                type="button"
                onClick={() => {
                  dispatch(setTemplate({ [`${name}._mapped._index`]: index + 1 }))
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

export default FieldMapped;
