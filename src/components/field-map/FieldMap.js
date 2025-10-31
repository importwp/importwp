import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { getFieldMap, setTemplate } from '../../features/importer/importerSlice';
import FieldLabel from '../field-label/FieldLabel';
import FieldMapped from '../field/FieldMapped';
import Modal from '../modal/Modal';

const FieldMap = ({ show, field, name, onClose = () => { }, delimiter = false }) => {
  const [showModal, setShowModal] = useState(false);

  const map = useSelector((state) => getFieldMap(state, name));
  const dispatch = useDispatch();

  useEffect(() => {
    if (!showModal) {
      onClose();
    }
  }, [showModal]);

  useEffect(() => {
    setShowModal(show);
  }, [show]);

  const onChange = (event) => {
    const target = event.target;
    let value = target.value;

    if (value.length <= 1) {
      dispatch(setTemplate({ [target.name]: value }));
    }
  };



  return (
    <Modal
      show={showModal}
      title={`Field map`}
      onClose={() => setShowModal(!showModal)}
    >
      <p>Click on add row to add your first data map, the field value will be compared against this data map.</p>
      {delimiter !== false && <>
        <FieldLabel label="Delimiter" />
        <input
          type='text'
          className="iwp-field__input-wrapper"
          name={`${name}._mapped._delimiter`}
          id={`${name}._mapped._delimiter`}
          onChange={onChange}
          placeholder='Leave empty to use fields default'
          value={map.hasOwnProperty(`${name}._mapped._delimiter`) ? map[`${name}._mapped._delimiter`] : ''}
        /></>}

      <FieldMapped field={field} name={name} append_field_id={false} />
    </Modal>
  );
};

export default FieldMap;
