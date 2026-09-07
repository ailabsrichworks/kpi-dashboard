/**
 * A small, hand-picked set of inline icons (Heroicons-outline shapes,
 * MIT-licensed, redrawn as plain SVG paths — no new npm dependency) used
 * throughout the Platform's nav and page headers. Non-technical users
 * recognize a building or a target faster than they parse a label, and a
 * consistent icon-per-concept vocabulary is a big part of what makes a
 * multi-page system feel like one coherent product instead of nineteen
 * separate forms.
 */
import type { SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement>;

function base(paths: React.ReactNode) {
    return function Icon({ className = 'w-5 h-5', ...props }: IconProps) {
        return (
            <svg
                className={className}
                fill="none"
                stroke="currentColor"
                strokeWidth={1.8}
                viewBox="0 0 24 24"
                aria-hidden="true"
                {...props}
            >
                {paths}
            </svg>
        );
    };
}

export const HomeIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
    />,
);

export const BuildingIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M3.75 21h16.5M4.5 3h9a.75.75 0 01.75.75V21H4.5V3.75A.75.75 0 015.25 3H4.5zm0 0h.75M8.25 6.75h.008v.008H8.25V6.75zm0 3.75h.008v.008H8.25V10.5zm0 3.75h.008v.008H8.25v-.008zM11.25 6.75h.008v.008h-.008V6.75zm0 3.75h.008v.008h-.008V10.5zm0 3.75h.008v.008h-.008v-.008zM14.25 21v-3.375c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125V21m-6-13.5h6a.75.75 0 01.75.75V21h-7.5V8.25a.75.75 0 01.75-.75z"
    />,
);

export const UsersIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
    />,
);

export const TargetIcon = base(
    <>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3l2 2" />
        <circle cx="12" cy="12" r="9" strokeLinecap="round" strokeLinejoin="round" />
        <circle cx="12" cy="12" r="4.5" strokeLinecap="round" strokeLinejoin="round" />
    </>,
);

export const FlagIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M3 3v18M3 4.5h13.5l-1.5 3.75 1.5 3.75H3"
    />,
);

export const ClipboardCheckIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
    />,
);

export const ChecklistIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M18.75 18.75V9.375c0-.621-.504-1.125-1.125-1.125H8.25M18.75 18.75H8.25m5.625-15A2.25 2.25 0 0011.625 2.25H10.5a2.25 2.25 0 00-2.25 2.25v0c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v0zM8.25 8.25h9V21H4.5V9.375c0-.621.504-1.125 1.125-1.125h2.625zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
    />,
);

export const RocketIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.63 8.41m5.96 5.96a14.926 14.926 0 01-5.96-5.96m0 0a14.926 14.926 0 00-5.841 2.58m-.475 6.31a6 6 0 007.38-5.84m-7.38 5.84a6 6 0 01-.475-6.31c.126-.114.257-.223.394-.328m0 0a14.926 14.926 0 015.96-5.96"
    />,
);

export const ShieldCheckIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M9 12.75L11.25 15 15 9.75M21 12c0 4.556-3.04 8.512-7.5 9.646C9.04 20.512 6 16.556 6 12V6.75c0-.414.336-.75.75-.75h10.5c.414 0 .75.336.75.75V12z"
    />,
);

export const SparklesIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"
    />,
);

export const UserCircleIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"
    />,
);

export const LogoutIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"
    />,
);

export const UploadIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
    />,
);

export const AdjustmentsIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"
    />,
);

export const DocumentDuplicateIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"
    />,
);

export const ChevronRightIcon = base(<path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />);

export const ArrowLeftIcon = base(<path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />);

export const CheckCircleIcon = base(
    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />,
);

export const ExclamationTriangleIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
    />,
);

export const InfoIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
    />,
);

export const PlusIcon = base(<path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />);

export const XMarkIcon = base(<path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />);

export const MenuIcon = base(<path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />);

export const CreditCardIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M2.25 8.25h19.5M2.25 9h19.5v9.75a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5V9zm0 0V6.75a1.5 1.5 0 011.5-1.5h16.5a1.5 1.5 0 011.5 1.5V9M6 15.75h4.5"
    />
);

export const ChartBarIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M3 13.5h3.75V21H3v-7.5zM10.125 8.25h3.75V21h-3.75V8.25zM17.25 3h3.75v18h-3.75V3z"
    />,
);

export const HeartIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"
    />,
);

export const HierarchyIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M12 3v4.5m0 0H6.75A2.25 2.25 0 004.5 9.75V12m7.5-4.5h5.25A2.25 2.25 0 0119.5 9.75V12M4.5 12v2.25A2.25 2.25 0 006.75 16.5h.75m-3-4.5h3m9-4.5v2.25a2.25 2.25 0 01-2.25 2.25h-.75m3-4.5h-3m-6.75 9v1.5A1.5 1.5 0 009 21h1.5m-3-4.5h3m3 4.5v-1.5A1.5 1.5 0 0016.5 18H15m3 3v-1.5a1.5 1.5 0 00-1.5-1.5H15m0 3h3"
    />,
);

export const PuzzlePieceIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3.75.75 0 00-.826.826c.055.68.11 1.36.166 2.043a.75.75 0 01-.658.81 47.39 47.39 0 01-1.858-.02.75.75 0 00-.807.767c.04 2.08.207 4.13.495 6.147a.75.75 0 00.807.65 47.63 47.63 0 011.858.02.75.75 0 01.658.81c-.055.68-.111 1.36-.166 2.043a.75.75 0 00.826.826 48.39 48.39 0 004.163-.3.64.64 0 01.657.643v0c0 .355-.186.676-.401.959-.221.29-.349.634-.349 1.003 0 1.035 1.007 1.875 2.25 1.875s2.25-.84 2.25-1.875c0-.369-.128-.713-.349-1.003-.215-.283-.401-.604-.401-.959v0c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875"
    />,
);

export const LifebuoyIcon = base(
    <>
        <circle cx="12" cy="12" r="9" strokeLinecap="round" strokeLinejoin="round" />
        <circle cx="12" cy="12" r="3.75" strokeLinecap="round" strokeLinejoin="round" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M5.636 5.636l2.652 2.652m7.424 0l2.652-2.652M5.636 18.364l2.652-2.652m7.424 0l2.652 2.652" />
    </>,
);

export const CalendarIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0V11.25a2.25 2.25 0 012.25-2.25h13.5a2.25 2.25 0 012.25 2.25v7.5"
    />,
);

export const IdentificationIcon = base(
    <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5h-15A1.5 1.5 0 003 6v12a1.5 1.5 0 001.5 1.5zm6.375-7.5a2.625 2.625 0 100-5.25 2.625 2.625 0 000 5.25zM6 18v-.75a3 3 0 013-3h1.5a3 3 0 013 3V18"
    />,
);

export const FilterIcon = base(
    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />,
);

export const CogIcon = base(
    <>
        <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"
        />
        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </>,
);
