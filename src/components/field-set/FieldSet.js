import React, { useCallback, useMemo, useState } from 'react';
import PropTypes from 'prop-types';
import Field from '../field/Field';
import { Tooltip } from 'react-tooltip';
import { connect } from 'react-redux';
import {
  addMapFieldRow,
  getEnabledMap,
  getFieldMap,
  getRepeaterFields,
  removeMapFieldRow,
} from '../../features/importer/importerSlice';

function FieldSet({
  group,
  showSelectModal,
  parents = [],
  enabledFields = {},
  importer_id,
  map,
  repeaterMap,
  dispatch,
}) {
  const initialRows = useMemo(() => {
    return group &&
      group.type === 'repeatable' &&
      Array.isArray(repeaterMap) &&
      repeaterMap.length > 0 &&
      repeaterMap.length <= 3
      ? repeaterMap.map((_, index) => index)
      : [];
  }, [group, repeaterMap]);

  const [expandedRows, setExpandedRows] = useState(initialRows);

  const addRow = useCallback((nextId) => {
    const newIndex = Array.isArray(repeaterMap) ? repeaterMap.length : 0;
    dispatch(addMapFieldRow(nextId));
    setExpandedRows((current) =>
      current.includes(newIndex) ? current : [...current, newIndex]
    );
  }, [dispatch, repeaterMap]);

  const removeRow = useCallback((nextId, index) => {
    dispatch(removeMapFieldRow({ id: nextId, index }));
    setExpandedRows((current) =>
      current
        .filter((rowIndex) => rowIndex !== index)
        .map((rowIndex) => (rowIndex > index ? rowIndex - 1 : rowIndex))
    );
  }, [dispatch]);

  const isRowExpanded = useCallback((index) => {
    return expandedRows.indexOf(index) !== -1;
  }, [expandedRows]);

  const toggleRow = useCallback((index) => {
    setExpandedRows((current) => {
      if (current.indexOf(index) !== -1) {
        return current.filter((rowIndex) => rowIndex !== index);
      }
      return [...current, index];
    });
  }, []);

  const expandAll = useCallback(() => {
    setExpandedRows(repeaterMap.map((_, index) => index));
  }, [repeaterMap]);

  const collapseAll = useCallback(() => {
    setExpandedRows([]);
  }, []);

  const getRowSummary = useCallback((record, key, index) => {
    const prefix = `${key}.${index}.`;
    const truncate = (text) =>
      text.length > 60 ? `${text.substring(0, 57)}…` : text;
    const summaryFields = Array.isArray(group.row_summary)
      ? group.row_summary
      : [];

    const parts = [];

    summaryFields.forEach((entry) => {
      const fieldId = typeof entry === 'string' ? entry : entry?.field;
      if (!fieldId) {
        return;
      }

      const value = record[`${prefix}${fieldId}`] || '';
      const hideIf = typeof entry === 'object' ? entry.hide_if : undefined;
      const format = typeof entry === 'object' ? entry.format : null;

      if (!value || (hideIf !== undefined && value === hideIf)) {
        return;
      }

      if (format === 'paren') {
        parts.push(`(${value})`);
        return;
      }

      parts.push(parts.length === 0 ? value : `→ ${truncate(value)}`);
    });

    return parts.length > 0 ? parts.join(' ') : `Row ${index + 1}`;
  }, [group.row_summary]);

  const checkConditions = useCallback((condition, groupData) => {
    let relation = 'AND';
    let currentCondition = condition;
    if (typeof currentCondition.relation !== 'undefined') {
      relation = currentCondition.relation;

      currentCondition = Object.keys(currentCondition)
        .filter((key) => key !== 'relation')
        .map(function (key) {
          return currentCondition[key];
        });
    }

    if (
      Array.isArray(currentCondition[0]) ||
      (currentCondition[0] &&
        typeof currentCondition[0] === 'object' &&
        currentCondition[0].hasOwnProperty('relation'))
    ) {
      if (currentCondition.length === 0) {
        return true;
      }

      for (let i = 0; i < currentCondition.length; i++) {
        const row_result = checkConditions(currentCondition[i], groupData);

        if ('OR' === relation && true === row_result) {
          return true;
        }

        if ('AND' === relation && false === row_result) {
          return false;
        }
      }
      if ('OR' === relation) {
        return false;
      }
      return true;
    }

    const operator = currentCondition[1];
    const left = groupData[currentCondition[0]];
    switch (operator) {
      case '*=': // Contains
        if (
          typeof left === 'string' &&
          true === left.includes(currentCondition[2])
        ) {
          return true;
        }
        break;
      case '!*': // Not Contains
        if (
          typeof left === 'string' &&
          false === left.includes(currentCondition[2])
        ) {
          return true;
        }
        break;
      case '==': // Equals
        if (left === currentCondition[2]) {
          return true;
        }
        break;
      case '!=': // Not Equals
        if (left !== currentCondition[2]) {
          return true;
        }
        break;
    }

    return false;
  }, []);

  const displayFieldSet = useCallback((panel, groupData, groupItem, parentList) => {
    const parent_path = [...parentList, groupItem.id].join('.');

    if (
      enabledFields.hasOwnProperty(parent_path) &&
      enabledFields[parent_path] === false
    ) {
      return '';
    }

    if (typeof groupItem.condition !== 'undefined') {
      if (false === checkConditions(groupItem.condition, groupData)) {
        return '';
      }
    }

    return panel;
  }, [checkConditions, enabledFields]);

  const display = useCallback((panel, groupData, field, parentList) => {
    const parent_path = [...parentList, field.id].join('.');
    if (
      enabledFields.hasOwnProperty(parent_path) &&
      enabledFields[parent_path] === false
    ) {
      return '';
    }

    if (typeof field.condition !== 'undefined') {
      if (false === checkConditions(field.condition, groupData)) {
        return '';
      }
    }

    return panel;
  }, [checkConditions, enabledFields]);

  const removeGroupIndex = useCallback((data, offset = 1) => {
    return Object.keys(data).reduce((obj, key) => {
      const parts = key.split('.');
      obj[parts.splice(offset).join('.')] = data[key];
      return obj;
    }, {});
  }, []);

  const content = useCallback((groupData, name, parentList) => {
    const { fields, type: groupType } = group;

    const liClass =
      groupType !== 'repeatable' ? 'iwp-field--border' : 'iwp-field--repeater';

    const resolvedGroupData =
      !groupData.hasOwnProperty('row_base') &&
        map.hasOwnProperty('row_base')
        ? { ...groupData, row_base: map.row_base }
        : groupData;

    return (
      <ul className="iwp-fields">
        {fields.map((field) => {
          if (
            field.type === 'settings' &&
            typeof field.fields !== 'undefined'
          ) {
            return (
              <React.Fragment key={field.id}>
                <FieldSettingsPanel
                  field={field}
                  parents={parentList}
                  groupData={resolvedGroupData}
                  showSelectModal={showSelectModal}
                  importer_id={importer_id}
                  displayFieldSet={displayFieldSet}
                />
              </React.Fragment>
            );
          }

          return (
            <React.Fragment key={field.id}>
              {typeof field.fields !== 'undefined'
                ? displayFieldSet(
                  <li
                    className={
                      'iwp-field iwp-field--template ' +
                      liClass +
                      ' iwp-field--' +
                      groupType
                    }
                  >
                    <ConnectedFieldSet
                      id={`${parentList.join('.')}`}
                      group={field}
                      parents={parentList}
                      showSelectModal={showSelectModal}
                      importer_id={importer_id}
                    />
                  </li>,
                  resolvedGroupData,
                  field,
                  parentList
                )
                : display(
                  <li className={'iwp-field iwp-field--template ' + liClass}>
                    <Field
                      field={field}
                      name={name}
                      showSelectModal={showSelectModal}
                      importer_id={importer_id}
                    />
                  </li>,
                  resolvedGroupData,
                  field,
                  parentList
                )}
            </React.Fragment>
          );
        })}
      </ul>
    );
  }, [display, displayFieldSet, group, importer_id, map, showSelectModal]);

  const { type, id } = group;

  let currentParents = [...parents];

  if (type === 'repeatable') {
    currentParents.push(id);
    const key = currentParents.join('.');
    const rowCount = Array.isArray(repeaterMap) ? repeaterMap.length : 0;
    return (
      <div className="iwp-repeater__wrapper">
        {rowCount > 5 && (
          <div className="iwp-repeater__bulk-actions iwp-buttons">
            <button
              type="button"
              className="button button-link"
              onClick={expandAll}
            >
              Expand All
            </button>
            <button
              type="button"
              className="button button-link"
              onClick={collapseAll}
            >
              Collapse All
            </button>
          </div>
        )}
        <ul className="iwp-repeater">
          {(repeaterMap || []).map((record, index) => {
            const tempParents = [...currentParents, index];
            const expanded = isRowExpanded(index);
            return (
              <li
                key={`${id}_${index}`}
                className={
                  'iwp-repeater__row' +
                  (expanded ? ' iwp-repeater__row--expanded' : ' iwp-repeater__row--collapsed')
                }
              >
                <span className="iwp-repeater__index">
                  <span>{index + 1}</span>
                </span>
                <div className="iwp-repeater__summary">
                  <button
                    type="button"
                    className="iwp-repeater__toggle"
                    aria-expanded={expanded}
                    onClick={() => toggleRow(index)}
                  >
                    <span className="iwp-repeater__toggle-icon" aria-hidden="true">
                      {expanded ? '▾' : '▸'}
                    </span>
                    <span className="iwp-repeater__summary-text">
                      {getRowSummary(record, key, index)}
                    </span>
                  </button>
                </div>
                {expanded &&
                  content(
                    removeGroupIndex(record, 2),
                    key + '.' + index,
                    tempParents
                  )}
                <div className="iwp-field iwp-buttons iwp-repeater__buttons">
                  <Tooltip
                    id={'iwp-delete-tooltip-' + id + '-' + index}
                    effect="solid"
                    delayHide={300}
                    className="iwp-react-tooltip"
                  >
                    Delete Row
                  </Tooltip>

                  <button
                    onClick={() => removeRow(id, index)}
                    type="button"
                    title="Delete Row"
                    data-tooltip-content="Delete Row"
                    data-tooltip-id={'iwp-delete-tooltip-' + id + '-' + index}
                  >
                    Delete Row
                  </button>
                </div>
              </li>
            );
          })}
        </ul>
        <div className="iwp-repeater__actions">
          <div className="iwp-buttons">
            <button
              type="button"
              className="button button-secondary"
              onClick={() => addRow(id)}
            >
              Add Row
            </button>
          </div>
        </div>
      </div>
    );
  }

  currentParents.push(id);

  const field_key = currentParents.join('.');

  if (typeof map === 'undefined') {
    return '';
  }

  const tmp = Object.keys(map).filter((value) => {
    return value.startsWith(field_key + '.');
  });

  const groupData = tmp.reduce((obj, key) => {
    const pos = key.indexOf(id);
    if (pos > -1) {
      obj[key.substring(pos)] = map[key];
    }

    return obj;
  }, {});

  return content(removeGroupIndex(groupData, 1), field_key, currentParents);
}

FieldSet.propTypes = {
  group: PropTypes.object.isRequired,
  showSelectModal: PropTypes.func,
  parents: PropTypes.array,
  enabledFields: PropTypes.object,
  importer_id: PropTypes.number,
};

const mapStateToProps = (state, props) => ({
  enabledFields: getEnabledMap(state, props.id),
  map: getFieldMap(state, props.id),
  repeaterMap: getRepeaterFields(state, props.id),
});

// Nested FieldSet / settings panels must use the connected component so Redux
// injects map/enabledFields. The class version did this via const FieldSet = connect(...).
const ConnectedFieldSet = connect(mapStateToProps)(FieldSet);

function FieldSettingsPanel({
  field,
  parents,
  groupData,
  showSelectModal,
  importer_id,
  displayFieldSet,
}) {
  const [open, setOpen] = useState(false);
  const parentPath = parents.join('.');

  return displayFieldSet(
    <li className="iwp-field-settings">
      <button
        type="button"
        className="button button-primary"
        onClick={(event) => {
          event.preventDefault();
          event.stopPropagation();
          setOpen((current) => !current);
        }}
      >
        {open ? 'Hide ' : 'Show '}
        Settings
      </button>

      {open && (
        <ConnectedFieldSet
          id={parentPath}
          group={field}
          parents={parents}
          showSelectModal={showSelectModal}
          importer_id={importer_id}
        />
      )}
    </li>,
    groupData,
    field,
    parents
  );
}

FieldSettingsPanel.propTypes = {
  field: PropTypes.object.isRequired,
  parents: PropTypes.array.isRequired,
  groupData: PropTypes.object.isRequired,
  showSelectModal: PropTypes.func,
  importer_id: PropTypes.number,
  displayFieldSet: PropTypes.func.isRequired,
};

export default ConnectedFieldSet;
