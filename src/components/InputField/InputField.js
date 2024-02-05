import FieldLabel from "../field-label/FieldLabel";

export default function InputField({
    name = '',
    value = '',
    onChange = () => { },
    children,
}) {
    return (
        <div className="iwp-field__input-wrapper">
            <input className="iwp-field__input-wrapper" type="text" id={name} name={name} value={value} onChange={(e) => onChange(e.target.value)} />
            {children}
        </div>
    );
}