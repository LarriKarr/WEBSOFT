// objects/static/flowerbed.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const border = new THREE.Mesh(new THREE.CylinderGeometry(0.85, 0.9, 0.28, 16), new THREE.MeshStandardMaterial({ color: 0x9ca3af }));
                    border.position.y = 0.14;
                    const soil = new THREE.Mesh(new THREE.CylinderGeometry(0.78, 0.78, 0.29, 16), col2);
                    soil.position.y = 0.145;
                    g.add(border, soil);
                    const fCols = [0xef4444, 0xfacc15, 0xec4899, 0xa855f7, 0xf97316];
                    for (let i = 0; i < 12; i++) {
                        const ang = (i / 12) * Math.PI * 2;
                        const r = 0.25 + (i % 3) * 0.18;
                        const fl = new THREE.Mesh(new THREE.SphereGeometry(0.09, 8, 8), new THREE.MeshStandardMaterial({ color: fCols[i % fCols.length] }));
                        fl.position.set(Math.cos(ang) * r, 0.34, Math.sin(ang) * r);
                        g.add(fl);
                    }

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'flowerbed',
    category: 'static',
    staticType: 'flowerbed',
    radius: 0.45,
    build
};
