import React from 'react';

const ROW_DEFAULT = { left: '', condition: 'equal', right: '' };

const ExportFilter = ({ onFilterChange = () => {}, filters = [] }) => {
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
    let maxGroups =
      data && data.hasOwnProperty(`filters._index`)
        ? data[`filters._index`]
        : 0;

    for (let i = 0; i < maxGroups; i++) {
      let group = [];
      let maxRows =
        data && data.hasOwnProperty(`filters.${i}._index`)
          ? data[`filters.${i}._index`]
          : 0;
      for (let j = 0; j < maxRows; j++) {
        group.push({
          left:
            data && data.hasOwnProperty(`filters.${i}.${j}.left`)
              ? data[`filters.${i}.${j}.left`]
              : ROW_DEFAULT.left,
          condition:
            data && data.hasOwnProperty(`filters.${i}.${j}.condition`)
              ? data[`filters.${i}.${j}.condition`]
              : ROW_DEFAULT.condition,
          right:
            data && data.hasOwnProperty(`filters.${i}.${j}.right`)
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
          Filter Records
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
                    <tr colSpan={4}>
                      <td>Or</td>
                    </tr>
                  )}
                  {group.map((row, row_index) => (
                    <tr key={`row-${group_index}-${row_index}`}>
                      <td>
                        <input
                          type="text"
                          name="left"
                          value={row.left}
                          onChange={(e) =>
                            updateRow(
                              e.target.value,
                              'left',
                              row_index,
                              group_index
                            )
                          }
                          style={{ width: '100%' }}
                          placeholder="Field name"
                        />
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
                          <option value="equal">Equals</option>
                          <option value="contains">Contains</option>
                          <option value="in">In</option>
                          <option value="not-equal">Not Equals</option>
                          <option value="not-contains">Not Contains</option>
                          <option value="not-in">Not In</option>
                        </select>
                      </td>
                      <td>
                        <input
                          type="text"
                          name="right"
                          value={row.right}
                          onChange={(e) =>
                            updateRow(
                              e.target.value,
                              'right',
                              row_index,
                              group_index
                            )
                          }
                          style={{ width: '100%' }}
                        />
                      </td>
                      <td>
                        <button
                          type="button"
                          className="button button-secondary"
                          onClick={() => addRow(group_index)}
                        >
                          And
                        </button>{' '}
                        <button
                          type="button"
                          className="button button-secondary"
                          onClick={() => removeRow(row_index, group_index)}
                        >
                          -
                        </button>
                      </td>
                    </tr>
                  ))}
                </React.Fragment>
              ))}
            </tbody>
            <thead>
              <tr>
                <th>Skip record if this</th>
                <th></th>
                <th>That</th>
                <th></th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <td colSpan={4}>
                  <button
                    type="button"
                    className="button button-secondary"
                    onClick={() => addGroup()}
                  >
                    Or
                  </button>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </div>
  );
};

export default ExportFilter;
