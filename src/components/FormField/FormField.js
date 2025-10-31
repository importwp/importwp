import './FormField.scss';

export default function FormField({ children }) {
    return (
        <div className="iwp-form__row">
            {children}
        </div>
    )
}