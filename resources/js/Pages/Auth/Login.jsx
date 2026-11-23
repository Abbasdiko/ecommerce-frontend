import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <main className="auth-page">
            <Head title="Sign in" />
            <section className="auth-card">
                <h1>Welcome back</h1>
                <p>Sign in to manage your orders and account.</p>
                <form onSubmit={submit}>
                    <label>Email<input type="email" autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required /></label>
                    {form.errors.email && <span className="form-error">{form.errors.email}</span>}
                    <label>Password<input type="password" autoComplete="current-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required /></label>
                    {form.errors.password && <span className="form-error">{form.errors.password}</span>}
                    <label className="checkbox-row"><input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} /> Remember me</label>
                    <button className="button" disabled={form.processing}>Sign in</button>
                </form>
                <p>New here? <Link href="/register">Create an account</Link></p>
            </section>
        </main>
    );
}
