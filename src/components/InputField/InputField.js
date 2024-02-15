import './InputField.scss';

export default function InputField({
    name = '',
    value = '',
    onChange = () => { },
    children,
    placeholder = ''
}) {
    return (
        <div className="iwp-input-field__wrapper">
            <input
                className="iwp-input-field iwp-input-field--text"
                type="text"
                id={name}
                name={name}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder} />
            {children}
        </div>
    );
}