import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/register');
    }

    return (
        <main className="auth-page">
            <Head title="Create account" />
            <section className="auth-card">
                <h1>Create your account</h1>
                <form onSubmit={submit}>
                    <div className="form-grid">
                        <label>First name<input value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} required /></label>
                        <label>Last name<input value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} required /></label>
                    </div>
                    <label>Email<input type="email" autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required /></label>
                    {form.errors.email && <span className="form-error">{form.errors.email}</span>}
                    <label>Phone (optional)<input type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></label>
                    <label>Password<input type="password" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required /></label>
                    <label>Confirm password<input type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required /></label>
                    {form.errors.password && <span className="form-error">{form.errors.password}</span>}
                    <button className="button" disabled={form.processing}>Create account</button>
                </form>
                <p>Already have an account? <Link href="/login">Sign in</Link></p>
            </section>
        </main>
    );
}
