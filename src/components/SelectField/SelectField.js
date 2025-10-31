import './SelectField.scss';

export default function SelectField({
    name = '',
    value = '',
    onChange = () => { },
    children,
    options = null,
    placeholder = ''
}) {
    return (
        <div className={`iwp-select-field__wrapper`}>
            <select
                id={name}
                name={name}
                value={value}
                className='iwp-select-field'
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
            >
                {options}
            </select>
            {children}
        </div>
    );
}