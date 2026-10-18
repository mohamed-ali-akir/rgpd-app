import { z } from 'zod'
import { CATEGORIES, DATA_TYPES, KINDS } from './labels'

/*
 * Schémas zod : ils décrivent la forme valide de chaque formulaire.
 * - react-hook-form les utilise (via zodResolver) pour afficher les erreurs avant l'envoi ;
 * - z.infer<typeof schema> en déduit le type TypeScript des valeurs : une seule source de vérité.
 * Le back revalide TOUT de son côté (FormRequest) : la validation front n'est qu'un confort.
 */

const requiredText = (message: string, max = 255) => z.string().trim().min(1, message).max(max, `${max} caractères maximum.`)

const optionalText = (max: number) =>
  z
    .string()
    .trim()
    .max(max, `${max} caractères maximum.`)
    // Champ vide → null, comme l'attend l'API.
    .transform((value) => (value === '' ? null : value))

const httpUrl = z
  .string()
  .trim()
  .min(1, 'Collez l\'adresse de la page.')
  .refine((value) => {
    try {
      const url = new URL(value)
      return (url.protocol === 'http:' || url.protocol === 'https:') && url.hostname.includes('.')
    } catch {
      return false
    }
  }, 'Adresse invalide : elle doit commencer par https:// (copiez-la depuis la barre d\'adresse).')

export const loginSchema = z.object({
  email: z.string().trim().min(1, 'Adresse e-mail requise.').email('Adresse e-mail invalide.'),
  password: z.string().min(1, 'Mot de passe requis.'),
})
export type LoginValues = z.infer<typeof loginSchema>

export const registerSchema = z
  .object({
    first_name: requiredText('Prénom requis.', 100),
    last_name: requiredText('Nom requis.', 100),
    email: z.string().trim().min(1, 'Adresse e-mail requise.').email('Adresse e-mail invalide.'),
    password: z.string().min(10, '10 caractères minimum.'),
    password_confirmation: z.string(),
  })
  // Règle qui porte sur deux champs : on place l'erreur sous la confirmation.
  .refine((values) => values.password === values.password_confirmation, {
    message: 'Les mots de passe ne correspondent pas.',
    path: ['password_confirmation'],
  })
export type RegisterValues = z.infer<typeof registerSchema>

export const forgotPasswordSchema = z.object({
  email: z.string().trim().min(1, 'Adresse e-mail requise.').email('Adresse e-mail invalide.'),
})
export type ForgotPasswordValues = z.infer<typeof forgotPasswordSchema>

export const resetPasswordSchema = z
  .object({
    password: z.string().min(10, '10 caractères minimum.'),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: 'Les mots de passe ne correspondent pas.',
    path: ['password_confirmation'],
  })
export type ResetPasswordValues = z.infer<typeof resetPasswordSchema>

export const profileSchema = z.object({
  first_name: requiredText('Prénom requis.', 100),
  last_name: requiredText('Nom requis.', 100),
  postal_address: optionalText(500),
  phone: optionalText(30),
  auto_scan: z.boolean(),
})
// input = ce que contient le formulaire ('' possible) ; output = ce qu'envoie l'API (null).
export type ProfileFormValues = z.input<typeof profileSchema>
export type ProfileFormOutput = z.output<typeof profileSchema>

export const organizationSchema = z.object({
  name: requiredText('Nom requis.'),
  domain: requiredText('Domaine requis.'),
  category: z.enum(CATEGORIES, 'Choisissez une catégorie.'),
  privacy_email: z
    .string()
    .trim()
    .refine((value) => value === '' || z.email().safeParse(value).success, 'Adresse e-mail invalide.')
    .transform((value) => (value === '' ? null : value)),
  privacy_form_url: z
    .string()
    .trim()
    .refine((value) => value === '' || httpUrl.safeParse(value).success, 'Adresse invalide.')
    .transform((value) => (value === '' ? null : value)),
  dpo_name: optionalText(255),
})
export type OrganizationFormValues = z.input<typeof organizationSchema>
export type OrganizationFormOutput = z.output<typeof organizationSchema>

/* --- Assistant de création d'une demande --- */

/** Étape 1 : l'URL de la page où apparaissent les données. */
export const urlStepSchema = z.object({ url: httpUrl })
export type UrlStepValues = z.infer<typeof urlStepSchema>

/** Étape 3 : données concernées et origine des données. */
export const dataStepSchema = z
  .object({
    kind: z.enum(KINDS),
    data_types: z.array(z.enum(DATA_TYPES)),
    details: z.string().trim().max(2000, '2000 caractères maximum.'),
    self_published: z.boolean(),
    has_account: z.boolean(),
    channel: z.enum(['gmail', 'manual']),
    own_data_confirmed: z.boolean().refine((checked) => checked, 'Vous devez confirmer que la demande concerne vos propres données.'),
  })
  // Règles qui dépendent du droit choisi (les mêmes que côté serveur, StoreErasureRequestRequest).
  .superRefine((values, ctx) => {
    if (values.kind !== 'access' && values.data_types.length === 0) {
      ctx.addIssue({ code: 'custom', path: ['data_types'], message: 'Cochez au moins un type de donnée.' })
    }
    if ((values.kind === 'rectification' || values.kind === 'restriction') && values.details === '') {
      ctx.addIssue({
        code: 'custom',
        path: ['details'],
        message: values.kind === 'rectification' ? 'Indiquez la correction demandée.' : 'Indiquez le motif de la limitation.',
      })
    }
  })
export type DataStepValues = z.input<typeof dataStepSchema>
