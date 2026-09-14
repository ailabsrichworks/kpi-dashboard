import { CSSProperties, ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';
import TopBar from '../Components/TopBar';
import AniraChatWidget from '../Components/AniraChatWidget';
import { SidebarProvider, useSidebar } from './SidebarContext';
import { SharedPageProps } from '../types';

function MainContent({ children }: { children: ReactNode }) {
    const { collapsed } = useSidebar();

    return (
        <main
            id="mainContent"
            className={`min-h-screen transition-all duration-300 ${collapsed ? 'ml-[64px]' : 'ml-[230px]'}`}
        >
            {children}
        </main>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const { props } = usePage<SharedPageProps>();
    const { layout } = props;

    // The user's own saved "main" appearance theme (Settings.tsx), applied
    // as CSS custom properties fresh on every render -- not relying on
    // app.blade.php's <head> <style> block for the actual VALUES (only for
    // the rules that consume them), since an Inertia SPA navigation never
    // re-renders <head> and so never picks up a value saved/changed after
    // the very first full-document load.
    const themeVars = {
        '--user-theme-bg': layout.themeBg,
        '--user-theme-card': layout.themeCard,
        '--user-theme-border': layout.themeBorder,
        '--user-theme-accent': layout.themeAccent,
        '--user-theme-accent2': layout.themeAccent2,
        '--user-theme-text': layout.themeText,
    } as CSSProperties;

    return (
        <SidebarProvider>
            <div className="min-h-screen" style={{ ...themeVars, backgroundColor: 'var(--user-theme-bg)' }}>
                <AniraChatWidget />
                <TopBar />
                <Sidebar />
                <MainContent>{children}</MainContent>
            </div>
        </SidebarProvider>
    );
}
