import { useId, type InputHTMLAttributes, type ReactNode, type Ref, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'

const inputClasses =
  'block w-full rounded-md border-0 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-600 focus:ring-inset aria-invalid:ring-red-500'

interface WrapperProps {
  id: string
  label: string
  error?: string
  hint?: ReactNode
  children: ReactNode
}

/** Libellé + champ + message d'aide ou d'erreur, reliés pour les lecteurs d'écran. */
function FieldWrapper({ id, label, error, hint, children }: WrapperProps) {
  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm font-medium text-slate-800">
        {label}
      </label>
      {children}
      {hint && !error && <p className="text-xs text-slate-500">{hint}</p>}
      {error && (
        <p id={`${id}-error`} className="text-sm text-red-600" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}

/*
 * En React 19, "ref" est une prop comme une autre : react-hook-form la transmet
 * via {...register('champ')} pour lire la valeur du champ HTML.
 */

interface InputFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  error?: string
  hint?: ReactNode
  ref?: Ref<HTMLInputElement>
}

export function InputField({ label, error, hint, ref, ...rest }: InputFieldProps) {
  // useId : identifiant unique et stable, pour relier <label htmlFor> et <input id>.
  const id = useId()
  return (
    <FieldWrapper id={id} label={label} error={error} hint={hint}>
      <input
        id={id}
        ref={ref}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        className={inputClasses}
        {...rest}
      />
    </FieldWrapper>
  )
}

interface TextareaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string
  error?: string
  hint?: ReactNode
  ref?: Ref<HTMLTextAreaElement>
}

export function TextareaField({ label, error, hint, ref, ...rest }: TextareaFieldProps) {
  const id = useId()
  return (
    <FieldWrapper id={id} label={label} error={error} hint={hint}>
      <textarea
        id={id}
        ref={ref}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        className={`${inputClasses} font-mono leading-relaxed`}
        {...rest}
      />
    </FieldWrapper>
  )
}

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string
  error?: string
  ref?: Ref<HTMLSelectElement>
  options: { value: string; label: string }[]
}

export function SelectField({ label, error, ref, options, ...rest }: SelectFieldProps) {
  const id = useId()
  return (
    <FieldWrapper id={id} label={label} error={error}>
      <select id={id} ref={ref} aria-invalid={error ? true : undefined} className={inputClasses} {...rest}>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </FieldWrapper>
  )
}

interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
  label: ReactNode
  ref?: Ref<HTMLInputElement>
}

export function Checkbox({ label, ref, ...rest }: CheckboxProps) {
  const id = useId()
  return (
    <div className="flex items-start gap-2">
      <input id={id} ref={ref} type="checkbox" className="mt-0.5 size-4 rounded border-slate-300 accent-indigo-600" {...rest} />
      <label htmlFor={id} className="text-sm text-slate-800">
        {label}
      </label>
    </div>
  )
}

export function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return (
    <p className="text-sm text-red-600" role="alert">
      {message}
    </p>
  )
}
