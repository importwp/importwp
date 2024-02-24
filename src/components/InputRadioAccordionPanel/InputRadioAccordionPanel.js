import './InputRadioAccordionPanel.scss';

export default function InputRadioAccordionPanel({
    label,
    children,
    name,
    value,
    isActive = false,
    setActive = () => { }
}) {

    const id = `${name}__${value}`;

    return (
        <div className='iwp-accordion__panel'>
            <div className='iwp-accordion__handle'>
                <input
                    type='radio'
                    id={id}
                    name={name}
                    value={value}
                    defaultChecked={isActive}
                    onChange={() => {
                        setActive(value);
                    }} />
                <label htmlFor={id}>{label}</label>
            </div>
            {children && (
                <div className='iwp-accordion__content' style={{
                    display: isActive ? 'block' : 'none'
                }}>
                    {children}
                </div>
            )}
        </div>
    );
}