import React from 'react';
import InputFieldDataSelector from '../InputFieldDataSelector/InputFieldDataSelector';
import InputField from '../InputField/InputField';
import InputButton from '../InputButton/InputButton';

const ROW_DEFAULT = { left: '', condition: 'equal', right: '' };

const ImportFilter = function ({ onFilterChange, filters }) {
  const save = function (data) {
    onFilterChange(flatten(data));
  };

  const addGroup = function () {
    save([...rows, [{ ...ROW_DEFAULT }]]);
  };

  const addRow = function (group_index) {
    let temp = [...rows];
    temp[group_index] = [...rows[group_index], { ...ROW_DEFAULT }];
    save(temp);
  };

  const removeRow = function (row_index, group_index) {
    let temp = [...rows];
    temp[group_index] = [
      ...temp[group_index].filter((value, index) => index !== row_index),
    ];

    if (temp[group_index].length === 0) {
      temp = [...temp.filter((value, index) => group_index !== index)];
    }

    save(temp);
  };

  const updateRow = function (value, key, row_index, group_index) {
    let temp = [...rows];
    temp[group_index][row_index][key] = value;
    save(temp);
  };

  const flatten = function (data = []) {
    let output = {};

    output[`filters._index`] = data.length;

    for (let i = 0; i < data.length; i++) {
      output[`filters.${i}._index`] = data[i].length;
      for (let j = 0; j < data[i].length; j++) {
        output[`filters.${i}.${j}.left`] = data[i][j].left;
        output[`filters.${i}.${j}.condition`] = data[i][j].condition;
        output[`filters.${i}.${j}.right`] = data[i][j].right;
      }
    }

    return output;
  };

  const unflatten = function (data = {}) {
    let output = [];
    let maxGroups = data.hasOwnProperty(`filters._index`)
      ? data[`filters._index`]
      : 0;

    for (let i = 0; i < maxGroups; i++) {
      let group = [];
      let maxRows = data.hasOwnProperty(`filters.${i}._index`)
        ? data[`filters.${i}._index`]
        : 0;
      for (let j = 0; j < maxRows; j++) {
        group.push({
          left: data.hasOwnProperty(`filters.${i}.${j}.left`)
            ? data[`filters.${i}.${j}.left`]
            : ROW_DEFAULT.left,
          condition: data.hasOwnProperty(`filters.${i}.${j}.condition`)
            ? data[`filters.${i}.${j}.condition`]
            : ROW_DEFAULT.condition,
          right: data.hasOwnProperty(`filters.${i}.${j}.right`)
            ? data[`filters.${i}.${j}.right`]
            : ROW_DEFAULT.right,
        });
      }

      output.push(group);
    }

    return output;
  };

  const rows = unflatten(filters);

  return (
    <div style={{ border: '1px solid #e2e2e2', marginBottom: '20px' }}>
      <div>
        <p style={{ padding: '8px 10px', margin: 0, display: 'inline-block' }}>
          Filter Rows
          {rows.length > 0 && (
            <>
              {' - '}
              <em>based on the following conditions.</em>
            </>
          )}
        </p>
        {rows.length === 0 && (
          <button
            type="button"
            onClick={() => addGroup()}
            style={{
              display: 'inline-block',
              cursor: 'pointer',
              background: 'none',
              border: 'none',
              color: '#22c48f',
              textDecoration: 'underline',
            }}
          >
            Add filter
          </button>
        )}
      </div>
      {rows.length > 0 && (
        <div
          style={{
            padding: '10px',
            background: '#f9f9f9',
            borderTop: '1px solid #e2e2e2',
          }}
        >
          <table style={{ width: '100%', textAlign: 'left' }}>
            <tbody>
              {rows.map((group, group_index) => (
                <React.Fragment key={`row-${group_index}`}>
                  {group_index > 0 && (
                    <tr colSpan={3}>
                      <td>Or</td>
                    </tr>
                  )}
                  {group.map((row, row_index) => {

                    const updateValue = (value) => {
                      updateRow(value, 'left', row_index, group_index)
                    }

                    return (
                      <tr key={`row-${group_index}-${row_index}`}>
                        <td>
                          <InputField
                            name="left"
                            value={row.left}
                            onChange={updateValue}
                            placeholder=''
                          >
                            <InputFieldDataSelector
                              value={row.left}
                              onClose={(selection) => {
                                updateValue(selection !== null ? selection : row.left);
                              }} />
                          </InputField>
                        </td>
                        <td>
                          <select
                            value={row.condition}
                            onChange={(e) =>
                              updateRow(
                                e.target.value,
                                'condition',
                                row_index,
                                group_index
                              )
                            }
                            style={{ width: '100%' }}
                          >
                            <option value="equal">Text Equals</option>
                            <option value="contains">Text Contains</option>
                            <option value="in">Text is listed in</option>
                            <option value="contains-in">
                              Contains text in list
                            </option>
                            <option value="not-equal">Text Not Equals</option>
                            <option value="not-contains">
                              Text Not Contains
                            </option>
                            <option value="not-in">Text not listed in</option>
                            <option value="not-contains-in">
                              Does not contain text in list
                            </option>
                          </select>
                        </td>
                        <td>
                          <InputField
                            name="right"
                            value={row.right}
                            onChange={(val) => updateRow(
                              val,
                              'right',
                              row_index,
                              group_index
                            )}
                            placeholder=''
                          >
                            <InputButton
                              theme='secondary'
                              onClick={() => addRow(group_index)}
                            >
                              And
                            </InputButton>
                            <InputButton
                              theme='secondary'
                              onClick={() => removeRow(row_index, group_index)}
                            >
                              -
                            </InputButton>
                          </InputField>
                        </td>
                      </tr>
                    )
                  })}
                </React.Fragment>
              ))}
            </tbody>
            <thead>
              <tr>
                <th>Skip row if this</th>
                <th></th>
                <th>That</th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <td colSpan={3}>
                  <InputButton
                    theme='secondary'
                    onClick={() => addGroup()}
                  >
                    Or
                  </InputButton>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </div>
  );
};

export default ImportFilter;
